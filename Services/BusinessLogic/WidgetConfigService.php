<?php

namespace Sequra\Core\Services\BusinessLogic;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Api\Data\StoreConfigInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\DataAccess\PromotionalWidgets\Entities\WidgetSettings as WidgetSettingsEntity;
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
use Sequra\Core\DataAccess\Entities\PaymentMethod;
use Sequra\Core\DataAccess\Entities\PaymentMethods as PaymentMethodsEntity;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\ServiceRegister;

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
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface                         $storeManager,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface   $localeResolver,
        StoreConfigManagerInterface $storeConfigManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->formatter = $this->getFormatter();
        $this->storeConfigManager = $storeConfigManager;
        $this->scopeConfig = $scopeConfig;
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

        $code = $this->getCountry($storeConfig);
        $merchantId = $this->getMerchantId($code, $isPreview);

        if (!$widgetSettings || !$merchantId || !$widgetSettings->isEnabled()) {
            return [];
        }

        $products = $this->getProducts($merchantId);
        $formattedProducts = [];

        foreach ($products as $product) {
            $formattedProducts[] = [
                'id' => $product->getProduct(),
                'campaign' => $product->getCampaign(),
            ];
        }

        return [
            'merchant' => $merchantId,
            'assetKey' => $widgetSettings->getAssetsKey(),
            'products' => $formattedProducts,
            'scriptUri' => $connectionSettings->getEnvironment() === BaseProxy::TEST_MODE ?
                self::TEST_SCRIPT_URL : self::LIVE_SCRIPT_URL,
            'decimalSeparator' => $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
            'thousandSeparator' => $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
            'locale' => str_replace('_', '-', $this->localeResolver->getLocale()),
            'currency' => 'EUR',
            'isProductListingEnabled' => $widgetSettings->isShowInstallmentsInProductListing(),
            'isProductEnabled' => $widgetSettings->isDisplayOnProductPage(),
            'widgetConfig' => $widgetSettings->getWidgetConfig(),
            'enabledStores' => $this->getEnabledStores(),
        ];
    }

    /**
     * @param string|null $code Country code
     * @param bool $isPreview Whether this is a preview mode request
     *
     * @return string
     */
    private function getMerchantId(?string $code, bool $isPreview): string
    {
        $merchantId = '';
        $countryConfig = $this->getCountryConfiguration();

        if (empty($countryConfig) || !$code) {
            return $merchantId;
        }

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

    private function getCountry(StoreConfigInterface $storeConfig)
    {
        return $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE,
            $storeConfig->getId()
        );
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
     * @return PaymentMethod[]
     *
     * @throws RepositoryNotRegisteredException
     * @throws QueryFilterInvalidParamException
     */
    private function getProducts(string $merchantId): array
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
     *
     * @throws RepositoryNotRegisteredException
     */
    private function getDefaultStore(): ?Store
    {
        $repository = RepositoryRegistry::getRepository(WidgetSettingsEntity::class);
        $settings = $repository->select();

        /** @var WidgetSettingsEntity $entity */
        foreach ($settings as $entity) {
            if ($entity->getWidgetSettings()->isEnabled()) {
                return $this->getStoreService()->getStoreById($entity->getStoreId());
            }
        }

        return null;
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    private function getEnabledStores(): array
    {
        $stores = $this->getStoreService()->getConnectedStores();
        $result = [];

        foreach ($stores as $store) {
            $widgetsConfig = AdminAPI::get()->widgetConfiguration($store)->getWidgetSettings()->toArray();

            if (isset($widgetsConfig['errorCode']) || !$widgetsConfig['useWidgets']) {
                continue;
            }

            $result[] = $store;
        }

        return $result;
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
