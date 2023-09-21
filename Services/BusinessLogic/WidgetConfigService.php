<?php

namespace Sequra\Core\Services\BusinessLogic;

use Exception;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\Domain\Stores\Models\Store;
use SeQura\Core\BusinessLogic\SeQuraAPI\BaseProxy;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ServiceRegister;

/**
 * Class WidgetConfigService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class WidgetConfigService
{
    public const TEST_SCRIPT_URL = 'https://sandbox.sequracdn.com/assets/sequra-checkout.min.js';
    public const LIVE_SCRIPT_URL = 'https://live.sequracdn.com/assets/sequra-checkout.min.js';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var \NumberFormatter
     */
    protected $formatter;
    /**
     * @var StoreConfigManagerInterface
     */
    private $storeConfigManager;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param StoreConfigManagerInterface $storeConfigManager
     */
    public function __construct(
        StoreManagerInterface                         $storeManager,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface   $localeResolver,
        StoreConfigManagerInterface $storeConfigManager
    )
    {
        $this->storeManager = $storeManager;
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->formatter = $this->getFormatter();
        $this->storeConfigManager = $storeConfigManager;
    }

    /**
     * @param string $storeId
     *
     * @return array[]
     *
     * @throws Exception
     */
    public function getData(string $storeId = ''): array
    {
        $store = $this->getDefaultStore();
        $isPreview = true;

        if ($storeId) {
            $store = $this->getStoreService()->getStoreById($storeId);
            $isPreview = false;
        }

        if (!$store) {
            return [];
        }

        return StoreContext::doWithStore($store->getStoreId(), function () use ($isPreview) {
            return $this->getConfigData($isPreview);
        });
    }

    /**
     * @return array[]
     *
     * @throws HttpRequestException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function getConfigData(bool $isPreview): array
    {
        $connectionSettings = $this->getConnectionSettings();

        if (!$connectionSettings) {
            return [];
        }

        $widgetSettings = $this->getWidgetSettings();
        $store = $this->storeManager->getStore(StoreContext::getInstance()->getStoreId());
        $storeConfig = $this->storeConfigManager->getStoreConfigs([$store->getCode()])[0];
        $code = substr($storeConfig->getLocale(), 3);
        $merchantId = $this->getMerchantId($code, $isPreview);

        if (!$widgetSettings || !$merchantId) {
            return [];
        }

        $products = $this->getProducts($merchantId);

        return [
            'merchant' => $merchantId,
            'assetKey' => $widgetSettings->getAssetsKey(),
            'products' => $products,
            'scriptUri' => $connectionSettings->getEnvironment() === BaseProxy::TEST_MODE ?
                self::TEST_SCRIPT_URL : self::LIVE_SCRIPT_URL,
            'decimalSeparator' => $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
            'thousandSeparator' => $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
            'locale' => str_replace('_', '-', $this->localeResolver->getLocale()),
            'currency' => $store->getDefaultCurrencyCode(),
            'isProductListingEnabled' => $widgetSettings->isShowInstallmentsInProductListing(),
            'isProductEnabled' => $widgetSettings->isDisplayOnProductPage(),
            'widgetConfig' => [
                'type' => $widgetSettings->getWidgetConfig()->getType(),
                'size' => $widgetSettings->getWidgetConfig()->getSize(),
                'font-color' => $widgetSettings->getWidgetConfig()->getFontColor(),
                'background-color' => $widgetSettings->getWidgetConfig()->getBackgroundColor(),
                'alignment' => $widgetSettings->getWidgetConfig()->getAlignment(),
                'branding' => $widgetSettings->getWidgetConfig()->getBranding(),
                'starting-text' => $widgetSettings->getWidgetConfig()->getStartingText(),
                'amount-font-size' => $widgetSettings->getWidgetConfig()->getAmountFontSize(),
                'amount-font-color' => $widgetSettings->getWidgetConfig()->getAmountFontColor(),
                'amount-font-bold' => $widgetSettings->getWidgetConfig()->getAmountFontBold(),
                'link-font-color' => $widgetSettings->getWidgetConfig()->getLinkFontColor(),
                'link-underline' => $widgetSettings->getWidgetConfig()->getLinkUnderline(),
                'border-color' => $widgetSettings->getWidgetConfig()->getBorderColor(),
                'border-radius' => $widgetSettings->getWidgetConfig()->getBorderRadius(),
                'no-costs-claim' => $widgetSettings->getWidgetConfig()->getNoCostsClaim(),
            ]
        ];
    }

    /**
     * @param string $code
     * @param bool $isPreview
     *
     * @return string
     */
    private function getMerchantId(string $code, bool $isPreview): string
    {
        $merchantId = '';
        $countryConfig = $this->getCountryConfiguration();

        if ($isPreview && isset($countryConfig[0])) {
            return $countryConfig[0]->getMerchantId();
        }

        foreach ($countryConfig as $item) {
            if ($item->getCountryCode() === $code) {
                $merchantId = $item->getMerchantId();
            }
        }

        return $merchantId;
    }

    /**
     * @return CountryConfiguration[]|null
     */
    private function getCountryConfiguration(): ?array
    {
        return $this->getCountryConfigService()->getCountryConfiguration();
    }

    /**
     * @return ConnectionData|null
     */
    private function getConnectionSettings(): ?ConnectionData
    {
        return $this->getConnectionService()->getConnectionData();
    }

    /**
     * @return WidgetSettings|null
     *
     * @throws Exception
     */
    private function getWidgetSettings(): ?WidgetSettings
    {
        return $this->getWidgetSettingsService()->getWidgetSettings();
    }

    /**
     * @param string $merchantId
     *
     * @return array
     *
     * @throws HttpRequestException
     */
    private function getProducts(string $merchantId): array
    {
        return $this->getPaymentMethodsService()->getMerchantProducts($merchantId);
    }

    private function getFormatter(): \NumberFormatter
    {
        $localeCode = $this->localeResolver->getLocale();
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    /**
     * @return Store|null
     */
    private function getDefaultStore(): ?Store
    {
        return $this->getStoreService()->getDefaultStore();
    }

    /**
     * @return StoreServiceInterface
     */
    private function getStoreService(): StoreServiceInterface
    {
        return ServiceRegister::getService(StoreServiceInterface::class);
    }

    /**
     * @return WidgetSettingsService
     */
    private function getWidgetSettingsService(): WidgetSettingsService
    {
        return ServiceRegister::getService(WidgetSettingsService::class);
    }

    /**
     * @return ConnectionService
     */
    private function getConnectionService(): ConnectionService
    {
        return ServiceRegister::getService(ConnectionService::class);
    }

    /**
     * @return PaymentMethodsService
     */
    private function getPaymentMethodsService(): PaymentMethodsService
    {
        return ServiceRegister::getService(PaymentMethodsService::class);
    }

    /**
     * @return CountryConfigurationService
     */
    private function getCountryConfigService(): CountryConfigurationService
    {
        return ServiceRegister::getService(CountryConfigurationService::class);
    }
}