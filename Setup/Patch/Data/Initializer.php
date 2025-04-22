<?php

namespace Sequra\Core\Setup\Patch\Data;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Connection\Exceptions\BadMerchantIdException;
use SeQura\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidEnvironmentException;
use SeQura\Core\BusinessLogic\Domain\Connection\Exceptions\WrongCredentialsException;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\AuthorizationCredentials;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Exceptions\EmptyCountryConfigurationParameterException;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Exceptions\InvalidCountryCodeForConfigurationException;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\SellingCountry;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\SellingCountriesService;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\SeQuraAPI\BaseProxy;
use SeQura\Core\BusinessLogic\Utility\EncryptorInterface;
use SeQura\Core\Infrastructure\Configuration\Configuration;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException;
use Sequra\Core\Services\Bootstrap;
use Sequra\Core\Services\BusinessLogic\ConfigurationService;
use Sequra\Core\Setup\DatabaseHandler;

class Initializer implements DataPatchInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ConnectionService
     */
    private $connectionService;
    /**
     * @var GeneralSettingsService
     */
    private $generalSettingsService;
    /**
     * @var WidgetSettingsService
     * @phpstan-ignore-next-line
     */
    private $widgetSettingsService;
    /**
     * @var SellingCountriesService
     */
    private $sellingCountriesService;
    /**
     * @var CountryConfigurationService
     */
    private $countryConfigService;
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var DatabaseHandler
     */
    protected $databaseHandler;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Bootstrap $bootstrap
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ScopeConfigInterface     $scopeConfig,
        StoreManagerInterface    $storeManager,
        Bootstrap                $bootstrap
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->databaseHandler = new DatabaseHandler($this->moduleDataSetup);

        $bootstrap->initInstance();
    }

    /**
     * Gets the dependencies for this patch.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Gets the aliases for this patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Applies the data patch.
     *
     * @return void
     */
    public function apply()
    {
        try {
            Logger::logInfo('Started executing V2.5.0.0 update script.');

            $this->initializerTaskRunner();

            $storeViews = $this->storeManager->getStores();
            /**
             * @var string $defaultUsername
             */
            $defaultUsername = $this->scopeConfig->getValue('sequra/core/user_name');
            /**
             * @var string $defaultPassword
             */
            $defaultPassword = $this->scopeConfig->getValue('sequra/core/user_secret') ?? '';
            $defaultPassword = $this->getEncryptor()->decrypt($defaultPassword);
            /**
             * @var string $defaultMerchantId
             */
            $defaultMerchantId = $this->scopeConfig->getValue('sequra/core/merchant_ref');
            /**
             * @var string $defaultAssetsKey
             */
            $defaultAssetsKey = $this->scopeConfig->getValue('sequra/core/assets_key');
            /**
             * @var string $defaultTestIps
             */
            $defaultTestIps = $this->scopeConfig->getValue('sequra/core/test_ip');
            /**
             * @var string $defaultEndpoint
             */
            $defaultEndpoint = $this->scopeConfig->getValue('sequra/core/endpoint');

            foreach ($storeViews as $storeView) {
                try {
                    StoreContext::doWithStore(
                        (string) $storeView->getId(),
                        function () use (
                            $defaultUsername,
                            $defaultPassword,
                            $defaultMerchantId,
                            $defaultAssetsKey,
                            $defaultTestIps,
                            $defaultEndpoint
                        ) {
                            $this->migrateCredentials(
                                $defaultUsername,
                                $defaultPassword,
                                $defaultMerchantId,
                                $defaultAssetsKey,
                                $defaultTestIps,
                                $defaultEndpoint
                            );
                        }
                    );
                } catch (Exception $e) {
                    Logger::logError('Migration of credentials failed for store view ' .
                        $storeView->getId() . ' because ' . $e->getMessage());
                }
            }

            $this->removeObsoleteConfig();
            $this->removeObsoleteStatuses();

            Logger::logInfo('Update script V2.5.0.0 executed successfully.');
        } catch (TaskRunnerStatusStorageUnavailableException $e) {
            Logger::logInfo('Update script V2.5.0.0 execution failed because: ' . $e->getMessage());
        }
    }

    /**
     * Initializes the task runner status.
     *
     * @return void
     *
     * @throws TaskRunnerStatusStorageUnavailableException
     */
    private function initializerTaskRunner()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $configService->setTaskRunnerStatus('', 0);
    }

    /**
     * Migrates credentials from the old configuration to the new one.
     *
     * @param string|null $defaultUsername Default username from global configuration
     * @param string|null $defaultPassword Default password from global configuration
     * @param string|null $defaultMerchantId Default merchant ID from global configuration
     * @param string|null $defaultAssetsKey Default assets key from global configuration
     * @param string|null $defaultTestIps Default test IPs from global configuration
     * @param string|null $defaultEndpoint Default endpoint from global configuration
     *
     * @return void
     *
     * @throws InvalidEnvironmentException
     * @throws Exception
     */
    private function migrateCredentials(
        ?string $defaultUsername,
        ?string $defaultPassword,
        ?string $defaultMerchantId,
        ?string $defaultAssetsKey,
        ?string $defaultTestIps,
        ?string $defaultEndpoint
    ) {
        $storeId = StoreContext::getInstance()->getStoreId();
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();

        /**
         * @var string $username
         */
        $username = $this->scopeConfig->getValue(
            'sequra/core/user_name',
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
        /**
         * @var string $password
         */
        $password = $this->scopeConfig->getValue(
            'sequra/core/user_secret',
            ScopeInterface::SCOPE_STORES,
            $storeId
        ) ?? '';
        $password = $this->getEncryptor()->decrypt($password);
        /**
         * @var string $merchantId
         */
        $merchantId = $this->scopeConfig->getValue(
            'sequra/core/merchant_ref',
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
        /**
         * @var string $assetsKey
         */
        $assetsKey = $this->scopeConfig->getValue(
            'sequra/core/assets_key',
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
        /**
         * @var string $testIps
         */
        $testIps = $this->scopeConfig->getValue(
            'sequra/core/test_ip',
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
        /**
         * @var string $endpoint
         */
        $endpoint = $this->scopeConfig->getValue(
            'sequra/core/endpoint',
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
        $sellingCountries = $this->getSellingCountriesService()->getSellingCountries();

        if (empty($username) || empty($password) || empty($merchantId) ||
            empty($assetsKey) || empty($endpoint)) {
            /**
             * @var string $username
             */
            $username = $this->scopeConfig->getValue(
                'sequra/core/user_name',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
            /**
             * @var string $password
             */
            $password = $this->scopeConfig->getValue(
                'sequra/core/user_secret',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            ) ?? '';
            $password = $this->getEncryptor()->decrypt($password);
            /**
             * @var string $merchantId
             */
            $merchantId = $this->scopeConfig->getValue(
                'sequra/core/merchant_ref',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
            /**
             * @var string $assetsKey
             */
            $assetsKey = $this->scopeConfig->getValue(
                'sequra/core/assets_key',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
            /**
             * @var string $testIps
             */
            $testIps = $this->scopeConfig->getValue(
                'sequra/core/test_ip',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
            /**
             * @var string $endpoint
             */
            $endpoint = $this->scopeConfig->getValue(
                'sequra/core/endpoint',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
        }

        if (empty($username) || empty($password) || empty($merchantId) ||
            empty($assetsKey) || empty($endpoint)) {
            /**
             * @var string $username
             */
            $username = $defaultUsername;
            /**
             * @var string $password
             */
            $password = $defaultPassword;
            /**
             * @var string $merchantId
             */
            $merchantId = $defaultMerchantId;
            /**
             * @var string $assetsKey
             */
            $assetsKey = $defaultAssetsKey;
            /**
             * @var string $testIps
             */
            $testIps = $defaultTestIps;
            /**
             * @var string $endpoint
             */
            $endpoint = $defaultEndpoint;
        }

        if (empty($username) || empty($password) || empty($merchantId) ||
            empty($endpoint)) {
            return;
        }

        $this->validateCredentials($endpoint, $username, $password, $merchantId);
        $this->saveConnectionData($endpoint, $username, $password);

        if (empty($sellingCountries)) {
            return;
        }

        $this->saveCountriesConfig($sellingCountries, $merchantId);

        $ipAddresses = is_string($testIps) ? explode(',', $testIps) : [];

        foreach ($ipAddresses as $key => $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP)) {
                unset($ipAddresses[$key]);
            }
        }

        $generalSettings = new GeneralSettings(
            false,
            true,
            $ipAddresses,
            [],
            []
        );
        $this->getGeneralSettingsService()->saveGeneralSettings($generalSettings);
        $this->saveWidgetSettings($assetsKey);
    }

    /**
     * Validates the credentials for the Sequra API.
     *
     * @param string $endpoint
     * @param string $username
     * @param string $password
     * @param string $merchantId
     *
     * @return void
     *
     * @throws InvalidEnvironmentException
     * @throws BadMerchantIdException
     * @throws WrongCredentialsException
     * @throws HttpRequestException
     */
    private function validateCredentials(string $endpoint, string $username, string $password, string $merchantId)
    {
        $connectionData = new ConnectionData(
            $endpoint === 'https://sandbox.sequrapi.com/orders' ? BaseProxy::TEST_MODE : BaseProxy::LIVE_MODE,
            $merchantId,
            new AuthorizationCredentials($username, $password)
        );

        $this->getConnectionService()->isConnectionDataValid($connectionData);
    }

    /**
     * Saves the connection data to the database.
     *
     * @param string $endpoint
     * @param string $username
     * @param string $password
     *
     * @return void
     *
     * @throws InvalidEnvironmentException
     */
    private function saveConnectionData(string $endpoint, string $username, string $password)
    {
        $connectionData = new ConnectionData(
            $endpoint === 'https://sandbox.sequrapi.com/orders' ? BaseProxy::TEST_MODE : BaseProxy::LIVE_MODE,
            '',
            new AuthorizationCredentials($username, $password)
        );
        $this->getConnectionService()->saveConnectionData($connectionData);
    }

    /**
     * Saves the countries configuration to the database.
     *
     * @param SellingCountry[] $sellingCountries
     * @param string $merchantId
     *
     * @return void
     *
     * @throws EmptyCountryConfigurationParameterException
     * @throws InvalidCountryCodeForConfigurationException
     */
    private function saveCountriesConfig(array $sellingCountries, string $merchantId)
    {
        $countryConfiguration = [];
        foreach ($sellingCountries as $country) {
            $countryConfiguration[] = new CountryConfiguration($country->getCode(), $merchantId);
        }

        $this->getCountryConfigService()->saveCountryConfiguration($countryConfiguration);
    }

    /**
     * Saves the widget settings to the database.
     *
     * @param string $assetsKey
     *
     * @return void
     *
     * @throws HttpRequestException
     * @throws Exception
     */
    private function saveWidgetSettings(string $assetsKey)
    {
        if (!$this->getWidgetSettingsService()->isAssetsKeyValid($assetsKey)) {
            return;
        }

        $widgetSettings = new WidgetSettings(false, $assetsKey);
        $this->getWidgetSettingsService()->setWidgetSettings($widgetSettings);
    }

    /**
     * Removes obsolete configuration from the database.
     *
     * @return void
     */
    private function removeObsoleteConfig()
    {
        $installer = $this->databaseHandler->getInstaller();
        $connection = $installer->getConnection();

        $connection->delete('core_config_data', "path like '%sequra%'");
    }

    /**
     * Removes obsolete statuses from the database.
     *
     * @return void
     */
    private function removeObsoleteStatuses()
    {
        $installer = $this->databaseHandler->getInstaller();
        $connection = $installer->getConnection();

        $connection->delete('sales_order_status', "status like '%sequra%'");
    }

    /**
     * Gets the connection service.
     *
     * @return ConnectionService
     */
    private function getConnectionService(): ConnectionService
    {
        if ($this->connectionService === null) {
            $this->connectionService = ServiceRegister::getService(ConnectionService::class);
        }

        return $this->connectionService;
    }

    /**
     * Gets the general settings service.
     *
     * @return GeneralSettingsService
     */
    private function getGeneralSettingsService(): GeneralSettingsService
    {
        if ($this->generalSettingsService === null) {
            $this->generalSettingsService = ServiceRegister::getService(GeneralSettingsService::class);
        }

        return $this->generalSettingsService;
    }

    /**
     * Gets the widget settings service.
     *
     * @return WidgetSettingsService
     */
    private function getWidgetSettingsService(): WidgetSettingsService
    {
        return ServiceRegister::getService(WidgetSettingsService::class);
    }

    /**
     * Get the selling countries service.
     *
     * @return SellingCountriesService
     */
    private function getSellingCountriesService(): SellingCountriesService
    {
        if ($this->sellingCountriesService === null) {
            $this->sellingCountriesService = ServiceRegister::getService(SellingCountriesService::class);
        }

        return $this->sellingCountriesService;
    }

    /**
     * Gets the country configuration service.
     *
     * @return CountryConfigurationService
     */
    private function getCountryConfigService(): CountryConfigurationService
    {
        if ($this->countryConfigService === null) {
            $this->countryConfigService = ServiceRegister::getService(CountryConfigurationService::class);
        }

        return $this->countryConfigService;
    }

    /**
     * Get the encryptor service.
     *
     * @return EncryptorInterface
     */
    private function getEncryptor(): EncryptorInterface
    {
        return ServiceRegister::getService(EncryptorInterface::class);
    }
}
