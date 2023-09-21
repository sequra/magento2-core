<?php

namespace Sequra\Core\Plugin;

use Exception;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Render\Amount;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Models\SeQuraPaymentMethod;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ProductService;

/**
 * Class MiniWidgets
 *
 * @package Sequra\Core\Plugin
 */
class MiniWidgets
{
    const MINI_WIDGET_PRODUCTS = ['sp1', 'pp3', 'pp6', 'pp9'];

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var StoreConfigManagerInterface
     */
    private $storeConfigManager;
    /**
     * @var ProductRepository
     */
    private $productRepository;
    /**
     * @var ProductService
     */
    private $productService;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param ProductRepository $productRepository
     * @param ProductService $productService
     */
    public function __construct(
        StoreManagerInterface       $storeManager,
        StoreConfigManagerInterface $storeConfigManager,
        ProductRepository           $productRepository,
        ProductService              $productService
    )
    {
        $this->storeManager = $storeManager;
        $this->storeConfigManager = $storeConfigManager;
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    /**
     * @param Amount $subject
     * @param $result
     *
     * @return string
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function afterToHtml(Amount $subject, $result): string
    {
        if ($subject->getData('zone') !== 'item_list') {
            return $result;
        }
        $store = $this->storeManager->getStore();
        $product = $subject->getPrice()->getProduct();

        $amount = (int)round($subject->getPrice()->getValue() * 100);
        $result .= StoreContext::doWithStore($store->getId(), function () use ($amount, $store, $product) {
            return $this->getHtml($amount, $store, $product);
        });

        return $result;
    }

    /**
     * @param int $amount
     * @param StoreInterface $store
     * @param SaleableInterface $product
     *
     * @return string
     *
     * @throws HttpRequestException
     * @throws Exception
     */
    private function getHtml(int $amount, StoreInterface $store, SaleableInterface $product): string
    {
        $result = '';

        $storeConfig = $this->storeConfigManager->getStoreConfigs([$store->getCode()])[0];

        if ($storeConfig->getBaseCurrencyCode() !== 'EUR') {
            return $result;
        }

        $code = substr($storeConfig->getLocale(), 3);
        $widgetConfig = $this->getWidgetSettingsService()->getWidgetSettings();
        $merchantId = $this->getMerchantId($code);

        if (empty($merchantId) || empty($widgetConfig) || !$widgetConfig->isShowInstallmentsInProductListing()
            || !$this->isWidgetEnabledForProduct($product)) {
            return $result;
        }

        $paymentMethods = $this->getPaymentMethods($merchantId);

        foreach ($paymentMethods as $paymentMethod) {
            if (!in_array($paymentMethod->getProduct(), self::MINI_WIDGET_PRODUCTS)) {
                continue;
            }

            $result .= $this->getWidgetHtml($widgetConfig, $storeConfig, $paymentMethod, $amount);
        }

        return $result;
    }

    /**
     * @param SaleableInterface $saleable
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    private function isWidgetEnabledForProduct(SaleableInterface $saleable): bool
    {
        $generalSettings = $this->getGeneralSettings();

        if (empty($generalSettings)) {
            return true;
        }

        $product = $this->productRepository->getById($saleable->getId());
        $categoryIds = $product->getCategoryIds();
        $trail = $this->productService->getAllProductCategories($categoryIds);

        return !in_array($product->getSku(), $generalSettings->getExcludedProducts())
            && empty(array_intersect($trail, $generalSettings->getExcludedCategories()));
    }

    /**
     * @param WidgetSettings $widgetConfig
     * @param StoreConfigInterface $storeConfig
     * @param SeQuraPaymentMethod $paymentMethod
     * @param int $amount
     *
     * @return string
     */
    private function getWidgetHtml(
        WidgetSettings       $widgetConfig,
        StoreConfigInterface $storeConfig,
        SeQuraPaymentMethod  $paymentMethod,
        int                  $amount
    ): string
    {
        $label = $widgetConfig->getWidgetLabels()->getMessages()[$storeConfig->getLocale()] ?? '';
        if ($paymentMethod->getMinAmount() > $amount) {
            $label = $widgetConfig->getWidgetLabels()->getMessagesBelowLimit()[$storeConfig->getLocale()] ?? '';
        }

        $message = $widgetConfig->getWidgetLabels()->getMessages()[$storeConfig->getLocale()] ?? '';
        $formattedMessage = sprintf($message, $amount);
        $belowLimit = $widgetConfig->getWidgetLabels()->getMessagesBelowLimit()[$storeConfig->getLocale()] ?? '';
        $formattedBelowLimit = sprintf($belowLimit, $amount);

        return "<div class=\"sequra-educational-popup\" data-content-type='Sequra_Core' data-amount=\""
            . $amount . "\" data-product=\"" . $paymentMethod->getProduct() . "\"
                data-min-amount='" . $paymentMethod->getMinAmount() . "' data-label='" . $formattedMessage . "'
                data-below-limit='" . $formattedBelowLimit . "'>"
            . sprintf($label, $amount) . "</div>";
    }

    /**
     * @param string $code
     *
     * @return string
     */
    private function getMerchantId(string $code): string
    {
        $merchantId = '';
        $countryConfig = $this->getCountryConfiguration();

        if (empty($countryConfig)) {
            return $merchantId;
        }

        foreach ($countryConfig as $item) {
            if ($item->getCountryCode() === $code) {
                $merchantId = $item->getMerchantId();
            }
        }

        return $merchantId;
    }

    /**
     * @param string $merchantId
     *
     * @return SeQuraPaymentMethod[]
     *
     * @throws HttpRequestException
     */
    private function getPaymentMethods(string $merchantId): array
    {
        return $this->getPaymentMethodsService()->getMerchantsPaymentMethods($merchantId);
    }

    /**
     * @return CountryConfiguration[]|null
     */
    private function getCountryConfiguration(): ?array
    {
        return $this->getCountryConfigService()->getCountryConfiguration();
    }

    /**
     * @return GeneralSettings|null
     */
    private function getGeneralSettings(): ?GeneralSettings
    {
        return $this->getSettingsService()->getGeneralSettings();
    }

    //<editor-fold desc="Service getters" defaultstate="collapsed">

    /**
     * @return CountryConfigurationService
     */
    private function getCountryConfigService(): CountryConfigurationService
    {
        return ServiceRegister::getService(CountryConfigurationService::class);
    }

    /**
     * @return WidgetSettingsService
     */
    private function getWidgetSettingsService(): WidgetSettingsService
    {
        return ServiceRegister::getService(WidgetSettingsService::class);
    }

    /**
     * @return PaymentMethodsService
     */
    private function getPaymentMethodsService(): PaymentMethodsService
    {
        return ServiceRegister::getService(PaymentMethodsService::class);
    }

    /**
     * @return GeneralSettingsService
     */
    private function getSettingsService(): GeneralSettingsService
    {
        return ServiceRegister::getService(GeneralSettingsService::class);
    }
    //</editor-fold>
}
