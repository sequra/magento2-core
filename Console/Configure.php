<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\Bootstrap;

/**
 * Console command to trigger DR report
 */
class Configure extends Command
{
    /**
     *  Command name
     */
    public const NAME = 'sequra:configure';

    /**
     * Names of input arguments or options
     */
    public const INPUT_KEY_MERCHANT_REF = 'merchant_ref';

    /**
     * Values of input arguments or options
     */
    public const INPUT_KEY_USERNAME = 'username';

    /**
     * Values of input arguments or options
     */
    public const INPUT_KEY_ASSETS_KEY = 'assets_key';

    /**
     * Values of input arguments or options
     */
    public const INPUT_KEY_ENDPOINT = 'endpoint';

    /**
     * Values of input arguments or options
     */
    public const INPUT_KEY_PASSWORD = 'password';

    /**
     * Values of input arguments or options
     */
    public const INPUT_KEY_STOREID = 'store_id';
    
    /**
     * @var ConnectionService
     */
    private $connectionService;

    /**
     * @var GeneralSettingsService
     */
    private $generalSettingsService;

    /**
     * @var SellingCountriesService
     */
    private $sellingCountriesService;

    /**
     * @var CountryConfigurationService
     */
    private $countryConfigService;

    /**
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        parent::__construct();
        $bootstrap->initInstance();
    }
    /**
     * Initialize triggerreport command
     *
     * @return void
     */
    protected function configure()
    {
        $inputOptions = [

        ];
        $this->setName(self::NAME)
            ->setDescription('Quickly set sequra configuration')
            ->addOption(
                self::INPUT_KEY_MERCHANT_REF,
                null,
                InputOption::VALUE_OPTIONAL,
                'Merchant reference'
            )
            ->addOption(
                self::INPUT_KEY_USERNAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'Username'
            )
            ->addOption(
                self::INPUT_KEY_ASSETS_KEY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Assets key'
            )
            ->addOption(
                self::INPUT_KEY_ENDPOINT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Endpoint'
            )
            ->addOption(
                self::INPUT_KEY_PASSWORD,
                null,
                InputOption::VALUE_OPTIONAL,
                'Password'
            )
            ->addOption(
                self::INPUT_KEY_STOREID,
                null,
                InputOption::VALUE_OPTIONAL,
                'Coma separated store ids'
            );
        parent::configure();
    }

    /**
     * Execute command.
     *
     * @param InputInterface  $input  InputInterface
     * @param OutputInterface $output OutputInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storeIds = $this->getStoreIds($input);
        foreach ($storeIds as $storeId) {
            try {
                // TODO: Use of echo language construct is discouraged.
                // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
                echo "Saving configuration to store Id " . $storeId;
                Logger::logError('Saving configuration to store Id ' . $storeId);
                StoreContext::doWithStore(
                    $storeId,
                    function () use ($input) {
                        $this->safeConfigDataForStore($input);
                    }
                );
            } catch (\Exception $e) {
                Logger::logError('Configuration could not be saved for store Id ' .
                    $storeId . ' because ' . $e->getMessage());
            }
        }
        return 0;
    }

    /**
     * Get store ids from input
     *
     * @param InputInterface $input
     *
     * @return string[]
     */
    protected function getStoreIds($input)
    {
        /**
         * @var string $storeIdOpt
         */
        $storeIdOpt = $input->getOption(self::INPUT_KEY_STOREID) ?? '1';
        $storeIds = explode(',', $storeIdOpt);
        if (count($storeIds) < 1) {
            $storeIds = ['1'];
        }
        return $storeIds;
    }

    /**
     * Save configuration data for store
     *
     * @param InputInterface $input
     *
     * @return void
     */
    protected function safeConfigDataForStore($input)
    {
        /**
         * @var string $endpoint
         */
        $endpoint = $input->getOption(self::INPUT_KEY_ENDPOINT);
        /**
         * @var string $username
         */
        $username = $input->getOption(self::INPUT_KEY_USERNAME);
        /**
         * @var string $password
         */
        $password = $input->getOption(self::INPUT_KEY_PASSWORD);
        /**
         * @var string $merchantId
         */
        $merchantId = $input->getOption(self::INPUT_KEY_MERCHANT_REF);
        /**
         * @var string $assetsKey
         */
        $assetsKey = $input->getOption(self::INPUT_KEY_ASSETS_KEY);

        $this->saveConnectionData($endpoint, $username, $password);
        $this->saveCountriesConfig($this->getSellingCountriesService()->getSellingCountries(), $merchantId);
        $generalSettings = new GeneralSettings(false, true, [], [], []);
        $this->getGeneralSettingsService()->saveGeneralSettings($generalSettings);
        $this->saveWidgetSettings($assetsKey);
    }

    /**
     * Save connection data
     *
     * @param string $endpoint
     * @param string $username
     * @param string $password
     *
     * @throws \SeQura\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidEnvironmentException
     *
     * @return void
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
     * Get connection service
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
     * Get general settings service
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
     * Save countries configuration
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
     * Get country configuration service
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
     * Get selling countries service
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
     * Save widget settings
     *
     * @param string $assetsKey
     *
     * @return void
     *
     * @throws \SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException
     * @throws \Exception
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
     * Get widget settings service
     *
     * @return WidgetSettingsService
     */
    private function getWidgetSettingsService(): WidgetSettingsService
    {
        return ServiceRegister::getService(WidgetSettingsService::class);
    }
}
