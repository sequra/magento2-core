<?php

namespace Sequra\Core\Plugin;

use Exception;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\Render\Amount;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use Sequra\Core\DataAccess\Entities\PaymentMethod;
use Sequra\Core\DataAccess\Entities\PaymentMethods as PaymentMethodsEntity;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
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
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param ProductRepository $productRepository
     * @param ProductService $productService
     * @param ScopeConfigInterface $scopeConfig
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        StoreManagerInterface       $storeManager,
        StoreConfigManagerInterface $storeConfigManager,
        ProductRepository           $productRepository,
        ProductService              $productService,
        ScopeConfigInterface        $scopeConfig,
        PriceCurrencyInterface      $priceCurrency
    )
    {
        $this->storeManager = $storeManager;
        $this->storeConfigManager = $storeConfigManager;
        $this->productRepository = $productRepository;
        $this->productService = $productService;
        $this->scopeConfig = $scopeConfig;
        $this->priceCurrency = $priceCurrency;
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
        if ($subject->getData('zone') !== 'item_list' || $subject->getData('price_type') !== 'finalPrice') {
            return $result;
        }
        $store = $this->storeManager->getStore();
        $product = $subject->getPrice()->getProduct();

        $amount = (int)round($subject->getPrice()->getAmount()->getValue() * 100);
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

        if ($this->priceCurrency->getCurrency()->getCurrencyCode() !== 'EUR') {
            return $result;
        }

        $code = $this->getCountry($storeConfig);

        $widgetConfig = $this->getWidgetSettingsService()->getWidgetSettings();
        $merchantId = $this->getMerchantId($code);
        $generalSettings = $this->getGeneralSettings();

        if (empty($merchantId) || empty($widgetConfig) || !$widgetConfig->isEnabled()
            || ($generalSettings && !empty($generalSettings->getAllowedIPAddresses())
                && !empty($ipAddress = $this->getCustomerIpAddress()) &&
                !in_array($ipAddress, $generalSettings->getAllowedIPAddresses(), true))
            || !$widgetConfig->isShowInstallmentsInProductListing()
            || !$this->isWidgetEnabledForProduct($product, $generalSettings)) {
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

    private function getCountry(StoreConfigInterface $storeConfig)
    {
        return $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE,
            $storeConfig->getId()
        );
    }

    /**
     * @param SaleableInterface $saleable
     * @param GeneralSettings|null $generalSettings
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    private function isWidgetEnabledForProduct(SaleableInterface $saleable, ?GeneralSettings $generalSettings): bool
    {
        $product = $this->productRepository->getById($saleable->getId());
        $categoryIds = $product->getCategoryIds();
        $trail = $this->productService->getAllProductCategories($categoryIds);

        return !in_array($product->getData('sku'), $generalSettings ? $generalSettings->getExcludedProducts() : [])
            && empty(array_intersect($trail, $generalSettings ? $generalSettings->getExcludedCategories() : []))
            && !$product->isVirtual() && $product->getTypeId() !== 'grouped';
    }

    /**
     * @param WidgetSettings $widgetConfig
     * @param StoreConfigInterface $storeConfig
     * @param PaymentMethod $paymentMethod
     * @param int $amount
     *
     * @return string
     */
    private function getWidgetHtml(
        WidgetSettings       $widgetConfig,
        StoreConfigInterface $storeConfig,
        PaymentMethod        $paymentMethod,
        int                  $amount
    ): string
    {
        $message = $widgetConfig->getWidgetLabels()->getMessages()[$storeConfig->getLocale()] ?? '';
        $belowLimit = $widgetConfig->getWidgetLabels()->getMessagesBelowLimit()[$storeConfig->getLocale()] ?? '';

        return "<div class=\"sequra-educational-popup\" data-content-type=\"sequra_core\" data-amount=\""
            . $amount . "\" data-product=\"" . $paymentMethod->getProduct() . "\"
                data-min-amount='" . $paymentMethod->getMinAmount() . "' data-label='" . $message . "'
                data-below-limit='" . $belowLimit . "'></div>";
    }

    /**
     * @param string|null $code
     *
     * @return string
     */
    private function getMerchantId(?string $code): string
    {
        $merchantId = '';
        $countryConfig = $this->getCountryConfiguration();

        if (empty($countryConfig) || !$code) {
            return $merchantId;
        }

        foreach ($countryConfig as $item) {
            if ($item->getCountryCode() === $code) {
                $merchantId = $item->getMerchantId();
            }
        }

        return $merchantId;
    }

    private function getCustomerIpAddress(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string $merchantId
     *
     * @return PaymentMethod[]
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    private function getPaymentMethods(string $merchantId): array
    {
        $paymentMethodsRepository = RepositoryRegistry::getRepository(PaymentMethodsEntity::CLASS_NAME);

        $filter = new QueryFilter();
        $filter->where('storeId', Operators::EQUALS, $this->storeManager->getStore()->getId())
            ->where('merchantId', Operators::EQUALS, $merchantId);

        /** @var PaymentMethodsEntity $paymentMethods */
        $paymentMethods = $paymentMethodsRepository->selectOne($filter);

        if ($paymentMethods === null) {
            return [];
        }

        return $paymentMethods->getPaymentMethods();
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
