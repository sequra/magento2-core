<?php

namespace Sequra\Core\Plugin;

use Exception;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\Render\Amount;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ProductService;

class MiniWidgets
{
    public const MINI_WIDGET_PRODUCTS = ['pp3'];

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
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;

    /**
     * @var \Magento\Framework\Escaper
     */
    private $htmlEscaper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param ProductRepository $productRepository
     * @param ProductService $productService
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\Escaper $htmlEscaper
     */
    public function __construct(
        StoreManagerInterface       $storeManager,
        StoreConfigManagerInterface $storeConfigManager,
        ProductRepository           $productRepository,
        ProductService              $productService,
        PriceCurrencyInterface      $priceCurrency,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Escaper $htmlEscaper
    ) {
        $this->storeManager = $storeManager;
        $this->storeConfigManager = $storeConfigManager;
        $this->productRepository = $productRepository;
        $this->productService = $productService;
        $this->priceCurrency = $priceCurrency;
        $this->localeResolver = $localeResolver;
        $this->htmlEscaper = $htmlEscaper;
    }

    /**
     * Runs after the toHtml method
     *
     * @param Amount $subject
     * @param string $result
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
        // TODO: Call to an undefined method Magento\Framework\Pricing\Price\PriceInterface::getProduct()
        // @phpstan-ignore-next-line
        $product = $subject->getPrice()->getProduct();

        $amount = (int)round($subject->getPrice()->getAmount()->getValue() * 100);
        $result .= StoreContext::doWithStore((string) $store->getId(), function () use ($amount, $store, $product) {
            return $this->getHtml($amount, $store, $product);
        });

        return $result;
    }

    /**
     * Get the HTML
     *
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

        // TODO: Call to an undefined method Magento\Framework\Model\AbstractModel::getCurrencyCode()
        // @phpstan-ignore-next-line
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

        /** @var CachedPaymentMethodsResponse $paymentMethods */
        $paymentMethods = CheckoutAPI::get()->cachedPaymentMethods($this->storeManager->getStore()->getId())
            ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($merchantId));

        if (!$paymentMethods->isSuccessful()) {
            return $result;
        }

        foreach ($paymentMethods->toArray() as $paymentMethod) {
            if (!is_array($paymentMethod) || !in_array($paymentMethod['product'], self::MINI_WIDGET_PRODUCTS)) {
                continue;
            }

            $minAmount = (int)($paymentMethod['minAmount'] ?? 0);
            if ($amount < $minAmount) {
                continue;
            }
            $maxAmount = isset($paymentMethod['maxAmount']) ? (int)$paymentMethod['maxAmount']: null;
            if (null !== $maxAmount && $maxAmount < $amount) {
                continue;
            }

            $result .= $this->getWidgetHtml(
                $widgetConfig,
                $storeConfig,
                $paymentMethod['product'] ?? '',
                $paymentMethod['minAmount'] ?? 0,
                $amount
            );
        }

        return $result;
    }

    /**
     * Gets the country code from store configuration
     *
     * @param StoreConfigInterface $storeConfig Store configuration
     *
     * @return string Country code
     */
    private function getCountry(StoreConfigInterface $storeConfig)
    {
        $parts = explode('_', $this->localeResolver->getLocale());
        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Checks if the widget is enabled for the product
     *
     * @param SaleableInterface $saleable
     * @param GeneralSettings|null $generalSettings
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    private function isWidgetEnabledForProduct(SaleableInterface $saleable, ?GeneralSettings $generalSettings): bool
    {
        /**
         * @var \Magento\Catalog\Model\Product $product
         */
        $product = $this->productRepository->getById($saleable->getId());
        $categoryIds = $product->getCategoryIds();
        $trail = $this->productService->getAllProductCategories($categoryIds);
        $excludedProducts = [];
        $excludedCategories = [];
        if ($generalSettings) {
            $excludedProducts = $generalSettings->getExcludedProducts() ?? [];
            $excludedCategories = $generalSettings->getExcludedCategories() ?? [];
        }

        return !in_array($product->getData('sku'), $excludedProducts)
            && empty(array_intersect($trail, $excludedCategories))
            && !$product->isVirtual() && $product->getTypeId() !== 'grouped';
    }

    /**
     * Get the widget HTML
     *
     * @param WidgetSettings $widgetConfig
     * @param StoreConfigInterface $storeConfig
     * @param string $product
     * @param int $minAmount
     * @param int $amount
     *
     * @return string
     */
    private function getWidgetHtml(
        WidgetSettings $widgetConfig,
        StoreConfigInterface $storeConfig,
        string $product,
        int $minAmount,
        int $amount
    ): string {

        $widgetLabels = $widgetConfig->getWidgetLabels();
        if (!$widgetLabels) {
            return '';
        }

        $message = $widgetLabels->getMessages()[$storeConfig->getLocale()] ?? '';
        $belowLimit = $widgetLabels->getMessagesBelowLimit()[$storeConfig->getLocale()] ?? '';

        $dataset = [
            'content-type' => 'sequra_core',
            'amount' => $amount,
            'product' => $product,
            'min-amount' => $minAmount,
            'label' => $message,
            'below-limit' => $belowLimit,
        ];

        $dataset = array_map(
            /**
             * @param string $key
             * @param string $value
             */
            function ($key, $value) {
                /**
                 * @var string $escapedValue
                 */
                $escapedValue = $this->htmlEscaper->escapeHtml((string) $value);
                return sprintf('data-%s="%s"', $key, $escapedValue);
            },
            array_keys($dataset),
            $dataset
        );
        $dataset = implode(' ', $dataset);

        return "<div class=\"sequra-educational-popup\" $dataset></div>";
    }

    /**
     * Gets the merchant ID from the country configuration
     *
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

    /**
     * Gets customer IP address from server globals
     *
     * @return string Customer IP address
     */
    private function getCustomerIpAddress(): string
    {
        // TODO: Look for an alternative to $_SERVER as it is not recommended to use it directly
        // phpcs:disable Magento2.Security.Superglobal.SuperglobalUsageWarning
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
        // phpcs:enable Magento2.Security.Superglobal.SuperglobalUsageWarning
    }

    /**
     * Gets the country configuration
     *
     * @return CountryConfiguration[]|null
     */
    private function getCountryConfiguration(): ?array
    {
        return $this->getCountryConfigService()->getCountryConfiguration();
    }

    /**
     * Gets the general settings
     *
     * @return GeneralSettings|null
     */
    private function getGeneralSettings(): ?GeneralSettings
    {
        return $this->getSettingsService()->getGeneralSettings();
    }

    /**
     * Gets the country configuration service
     *
     * @return CountryConfigurationService
     */
    private function getCountryConfigService(): CountryConfigurationService
    {
        return ServiceRegister::getService(CountryConfigurationService::class);
    }

    /**
     * Gets the widget settings service
     *
     * @return WidgetSettingsService
     */
    private function getWidgetSettingsService(): WidgetSettingsService
    {
        return ServiceRegister::getService(WidgetSettingsService::class);
    }

    /**
     * Get the general settings service
     *
     * @return GeneralSettingsService
     */
    private function getSettingsService(): GeneralSettingsService
    {
        return ServiceRegister::getService(GeneralSettingsService::class);
    }
}
