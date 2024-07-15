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
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\SeQuraAPI\BaseProxy;
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
    const NAME = 'sequra:configure';
    /**
     * Names of input arguments or options
     */
    const INPUT_KEY_MERCHANT_REF = 'merchant_ref';
    /**
     * Values of input arguments or options
     */
    const INPUT_KEY_USERNAME = 'username';
    /**
     * Values of input arguments or options
     */
    const INPUT_KEY_ASSETS_KEY = 'assets_key';
    /**
     * Values of input arguments or options
     */
    const INPUT_KEY_ENDPOINT = 'endpoint';
    /**
     * Values of input arguments or options
     */
    const INPUT_KEY_PASSWORD = 'password';

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
    public function __construct(
        Bootstrap                $bootstrap
    )
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
            );
        parent::configure();
    }

    /**
     * Execute command.
     *
     * @param InputInterface  $input  InputInterface
     * @param OutputInterface $output OutputInterface
     *
     * @return                                        void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->saveConnectionData(
            $input->getOption(self::INPUT_KEY_ENDPOINT),
            $input->getOption(self::INPUT_KEY_USERNAME),
            $input->getOption(self::INPUT_KEY_PASSWORD)
        );
        $this->saveCountriesConfig(
            $this->getSellingCountriesService()->getSellingCountries(),
            $input->getOption(self::INPUT_KEY_MERCHANT_REF)
        );
        $generalSettings = new GeneralSettings(
            false,
            true,
            [],
            [],
            []
        );
        $this->getGeneralSettingsService()->saveGeneralSettings($generalSettings);
        $this->saveWidgetSettings($input->getOption(self::INPUT_KEY_ASSETS_KEY));
        return 0;
    }

        /**
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
     * @return WidgetSettingsService
     */
    private function getWidgetSettingsService(): WidgetSettingsService
    {
        return ServiceRegister::getService(WidgetSettingsService::class);
    }
}