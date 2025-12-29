<?php

namespace Sequra\Core\Services;

use SeQura\Core\BusinessLogic\BootstrapComponent;
use SeQura\Core\BusinessLogic\DataAccess\ConnectionData\Entities\ConnectionData;
use SeQura\Core\BusinessLogic\DataAccess\CountryConfiguration\Entities\CountryConfiguration;
use SeQura\Core\BusinessLogic\DataAccess\Credentials\Entities\Credentials;
use SeQura\Core\BusinessLogic\DataAccess\Deployments\Entities\Deployment;
use SeQura\Core\BusinessLogic\DataAccess\GeneralSettings\Entities\GeneralSettings;
use SeQura\Core\BusinessLogic\DataAccess\OrderSettings\Entities\OrderStatusSettings;
use SeQura\Core\BusinessLogic\DataAccess\PaymentMethod\Entities\PaymentMethod;
use SeQura\Core\BusinessLogic\DataAccess\PromotionalWidgets\Entities\WidgetSettings;
use SeQura\Core\BusinessLogic\DataAccess\SendReport\Entities\SendReport;
use SeQura\Core\BusinessLogic\DataAccess\StatisticalData\Entities\StatisticalData;
use SeQura\Core\BusinessLogic\DataAccess\TransactionLog\Entities\TransactionLog;
use SeQura\Core\BusinessLogic\Domain\Integration\Category\CategoryServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Disconnect\DisconnectServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\MerchantDataProviderInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\OrderCreationInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\OrderReport\OrderReportServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Product\ProductServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\PromotionalWidgets\MiniWidgetMessagesProviderInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\PromotionalWidgets\WidgetConfiguratorInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\SellingCountries\SellingCountriesServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\ShopOrderStatuses\ShopOrderStatusesServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\StoreIntegration\StoreIntegrationServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Version\VersionServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\BusinessLogic\Domain\OrderStatusSettings\RepositoryContracts\OrderStatusSettingsRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\SendReport\RepositoryContracts\SendReportRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\StatisticalData\RepositoryContracts\StatisticalDataRepositoryInterface;
use SeQura\Core\BusinessLogic\Utility\EncryptorInterface;
use SeQura\Core\BusinessLogic\Webhook\Services\ShopOrderService;
use SeQura\Core\Infrastructure\Configuration\ConfigEntity;
use SeQura\Core\Infrastructure\Configuration\Configuration;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\Serializer\Concrete\JsonSerializer;
use SeQura\Core\Infrastructure\Serializer\Serializer;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Process;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;
use SeQura\Core\Infrastructure\Utility\TimeProvider;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder;
use Sequra\Core\Repository\BaseRepository;
use Sequra\Core\Repository\QueueItemRepository;
use Sequra\Core\Repository\SeQuraOrderRepository;
use Sequra\Core\Services\BusinessLogic\CategoryService;
use Sequra\Core\Services\BusinessLogic\ConfigurationService;
use Sequra\Core\Services\BusinessLogic\DisconnectService;
use Sequra\Core\Services\BusinessLogic\Order\MerchantDataProvider;
use Sequra\Core\Services\BusinessLogic\Order\OrderCreation;
use Sequra\Core\Services\BusinessLogic\OrderReportService;
use Sequra\Core\Services\BusinessLogic\OrderServiceFactory;
use Sequra\Core\Services\BusinessLogic\PaymentMethodsService;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Sequra\Core\Services\BusinessLogic\PromotionalWidget\MiniWidgetMessagesProvider;
use Sequra\Core\Services\BusinessLogic\PromotionalWidget\WidgetConfigurator;
use Sequra\Core\Services\BusinessLogic\SellingCountriesService;
use Sequra\Core\Services\BusinessLogic\ShopOrderStatusesService;
use Sequra\Core\Services\BusinessLogic\StatisticalDataService;
use Sequra\Core\Services\BusinessLogic\StoreIntegrationService;
use Sequra\Core\Services\BusinessLogic\StoreService;
use Sequra\Core\Services\BusinessLogic\Utility\Encryptor;
use Sequra\Core\Services\BusinessLogic\VersionService;
use Sequra\Core\Services\BusinessLogic\Webhook\Repositories\OrderStatusMappingRepositoryOverride;
use Sequra\Core\Services\Infrastructure\LoggerService;

class Bootstrap extends BootstrapComponent
{
    /**
     * Class instance.
     *
     * @var static
     */
    protected static $instance;
    /**
     * @var LoggerService
     */
    private $loggerService;
    /**
     * @var ConfigurationService
     */
    private $configurationService;
    /**
     * @var StoreService
     */
    private $storeService;
    /**
     * @var StoreIntegrationService
     */
    private $storeIntegrationService;
    /**
     * @var VersionService
     */
    private $versionService;
    /**
     * @var SellingCountriesService
     */
    private $sellingCountriesService;
    /**
     * @var CategoryService
     */
    private $categoryService;
    /**
     * @var DisconnectService
     */
    private $disconnectService;
    /**
     * @var OrderReportService
     */
    private $orderReportService;
    /**
     * @var Encryptor
     */
    private $encryptor;
    /**
     * @var OrderServiceFactory
     */
    private $orderServiceFactory;
    /** @var WidgetConfigurator */
    private $widgetConfigurator;

    /** @var MiniWidgetMessagesProvider */
    private $miniWidgetMessagesProvider;

    /** @var ProductService */
    private $productService;

    /** @var MerchantDataProvider */
    private $merchantDataProvider;

    /** @var OrderCreation */
    private $orderCreation;

    /**
     *  Constructor for Bootstrap
     *
     * @param LoggerService $loggerService
     * @param ConfigurationService $configurationService
     * @param StoreService $storeService
     * @param StoreIntegrationService $storeIntegrationService
     * @param VersionService $versionService
     * @param SellingCountriesService $sellingCountriesService
     * @param CategoryService $categoryService
     * @param DisconnectService $disconnectService
     * @param OrderReportService $orderReportService
     * @param Encryptor $encryptor
     * @param OrderServiceFactory $orderServiceFactory
     * @param WidgetConfigurator $widgetConfigurator
     * @param MiniWidgetMessagesProvider $miniWidgetMessagesProvider
     * @param ProductService $productService
     * @param MerchantDataProvider $merchantDataProvider
     * @param OrderCreation $orderCreation
     */
    public function __construct(
        LoggerService $loggerService,
        ConfigurationService $configurationService,
        StoreService $storeService,
        StoreIntegrationService $storeIntegrationService,
        VersionService $versionService,
        SellingCountriesService $sellingCountriesService,
        CategoryService $categoryService,
        DisconnectService $disconnectService,
        OrderReportService $orderReportService,
        Encryptor $encryptor,
        OrderServiceFactory $orderServiceFactory,
        WidgetConfigurator $widgetConfigurator,
        MiniWidgetMessagesProvider $miniWidgetMessagesProvider,
        ProductService $productService,
        MerchantDataProvider $merchantDataProvider,
        OrderCreation $orderCreation
    ) {
        $this->loggerService = $loggerService;
        $this->configurationService = $configurationService;
        $this->storeService = $storeService;
        $this->storeIntegrationService = $storeIntegrationService;
        $this->versionService = $versionService;
        $this->sellingCountriesService = $sellingCountriesService;
        $this->categoryService = $categoryService;
        $this->disconnectService = $disconnectService;
        $this->orderReportService = $orderReportService;
        $this->encryptor = $encryptor;
        $this->orderServiceFactory = $orderServiceFactory;
        $this->widgetConfigurator = $widgetConfigurator;
        $this->miniWidgetMessagesProvider = $miniWidgetMessagesProvider;
        $this->productService = $productService;
        $this->merchantDataProvider = $merchantDataProvider;
        $this->orderCreation = $orderCreation;

        static::$instance = $this;
    }

    /**
     * Initializes instance.
     */
    public function initInstance(): void
    {
        self::init();
    }

    // TODO: Static method cannot be intercepted and its use is discouraged.
    // phpcs:disable Magento2.Functions.StaticFunction.StaticFunction
    /**
     * @inheritDoc
     */
    protected static function initServices(): void
    {
        parent::initServices();

        $instance = static::$instance;

        ServiceRegister::registerService(
            Configuration::class,
            static function () {
                return static::$instance->configurationService;
            }
        );

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            static function () use ($instance) {
                return $instance->loggerService;
            }
        );

        ServiceRegister::registerService(
            Serializer::class,
            static function () {
                return new JsonSerializer();
            }
        );

        ServiceRegister::registerService(
            EncryptorInterface::class,
            static function () {
                return static::$instance->encryptor;
            }
        );

        ServiceRegister::registerService(
            ShopOrderService::class,
            function () {
                return static::$instance->orderServiceFactory->create([
                    'seQuraOrderRepository' => ServiceRegister::getService(SeQuraOrderRepositoryInterface::class),
                    'sequraOrderService' => ServiceRegister::getService(OrderService::class)
                ]);
            }
        );

        ServiceRegister::registerService(
            LoggerService::class,
            static function () {
                return static::$instance->loggerService;
            }
        );

        ServiceRegister::registerService(
            StoreServiceInterface::class,
            static function () {
                return static::$instance->storeService;
            }
        );

        ServiceRegister::registerService(
            StoreIntegrationServiceInterface::class,
            static function () {
                return static::$instance->storeIntegrationService;
            }
        );

        ServiceRegister::registerService(
            VersionServiceInterface::class,
            static function () {
                return static::$instance->versionService;
            }
        );

        ServiceRegister::registerService(
            SellingCountriesServiceInterface::class,
            static function () {
                return static::$instance->sellingCountriesService;
            }
        );

        ServiceRegister::registerService(
            CategoryServiceInterface::class,
            static function () {
                return static::$instance->categoryService;
            }
        );

        ServiceRegister::registerService(
            DisconnectServiceInterface::class,
            static function () {
                return static::$instance->disconnectService;
            }
        );

        ServiceRegister::registerService(
            OrderReportServiceInterface::class,
            static function () {
                return static::$instance->orderReportService;
            }
        );

        ServiceRegister::registerService(
            MerchantDataProviderInterface::class,
            static function () {
                return static::$instance->merchantDataProvider;
            }
        );

        ServiceRegister::registerService(
            OrderCreationInterface::class,
            static function () {
                return static::$instance->orderCreation;
            }
        );

        ServiceRegister::registerService(
            PaymentMethodsService::class,
            static function () {
                return new PaymentMethodsService();
            }
        );

        ServiceRegister::registerService(
            \SeQura\Core\BusinessLogic\Domain\StatisticalData\Services\StatisticalDataService::class,
            static function () {
                return new StatisticalDataService(
                    ServiceRegister::getService(StatisticalDataRepositoryInterface::class),
                    ServiceRegister::getService(SendReportRepositoryInterface::class),
                    ServiceRegister::getService(TimeProvider::class)
                );
            }
        );

        ServiceRegister::registerService(
            ShopOrderStatusesServiceInterface::class,
            static function () {
                return new ShopOrderStatusesService();
            }
        );

        ServiceRegister::registerService(
            WidgetConfiguratorInterface::class,
            static function () {
                return static::$instance->widgetConfigurator;
            }
        );

        ServiceRegister::registerService(
            MiniWidgetMessagesProviderInterface::class,
            static function () {
                return static::$instance->miniWidgetMessagesProvider;
            }
        );

        ServiceRegister::registerService(
            ProductServiceInterface::class,
            static function () {
                return static::$instance->productService;
            }
        );
    }

    /**
     * @inheritDoc
     *
     * @throws RepositoryClassException
     */
    protected static function initRepositories(): void
    {
        parent::initRepositories();

        RepositoryRegistry::registerRepository(ConfigEntity::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(QueueItem::class, QueueItemRepository::class);
        RepositoryRegistry::registerRepository(Process::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(ConnectionData::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(OrderStatusSettings::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(StatisticalData::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(CountryConfiguration::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(GeneralSettings::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(SeQuraOrder::class, SeQuraOrderRepository::class);
        RepositoryRegistry::registerRepository(WidgetSettings::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(SendReport::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(StatisticalData::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(TransactionLog::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(PaymentMethod::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(Credentials::class, BaseRepository::class);
        RepositoryRegistry::registerRepository(Deployment::class, BaseRepository::class);

        ServiceRegister::registerService(
            OrderStatusSettingsRepositoryInterface::class,
            static function () {
                return new OrderStatusMappingRepositoryOverride();
            }
        );
    }
    // phpcs:enable Magento2.Functions.StaticFunction.StaticFunction
}
