<?php

namespace Sequra\Core\Services\BusinessLogic;

use Exception;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\DB\Adapter\SqlVersionProvider;
use Magento\Framework\Module\ResourceInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\OrderReport\OrderReportServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Platform;
use SeQura\Core\BusinessLogic\Domain\OrderReport\Models\OrderStatistics;
use Sequra\Core\Services\BusinessLogic\Utility\TransformEntityService;

class OrderReportService implements OrderReportServiceInterface
{
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var ResourceInterface
     */
    private $moduleResource;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var SqlVersionProvider
     */
    private $sqlVersionProvider;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ProductMetadataInterface $productMetadata
     * @param ResourceInterface $moduleResource
     * @param DeploymentConfig $deploymentConfig
     * @param SqlVersionProvider $sqlVersionProvider
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        ResourceInterface        $moduleResource,
        DeploymentConfig         $deploymentConfig,
        SqlVersionProvider       $sqlVersionProvider,
        ResourceConnection       $resourceConnection
    ) {
        $this->productMetadata = $productMetadata;
        $this->moduleResource = $moduleResource;
        $this->deploymentConfig = $deploymentConfig;
        $this->sqlVersionProvider = $sqlVersionProvider;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     */
    public function getOrderReports(array $orderIds): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getOrderStatistics(array $orderIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesPaymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $salesAddressTable = $this->resourceConnection->getTableName('sales_order_address');

        $select = $connection->select()
            ->from(['o' => $salesOrderTable])
            ->joinLeft(['p' => $salesPaymentTable], 'o.entity_id = p.parent_id', ['method'])
            ->joinLeft(['a' => $salesAddressTable], 'o.billing_address_id = a.entity_id', ['country_id'])
            ->where('o.entity_id IN (?)', $orderIds);

        $result = $connection->fetchAll($select);

        $statistics = [];
        foreach ($result as $item) {
            $statistics[] = $this->createOrderStatistics($item);
        }

        return $statistics;
    }

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function getPlatform(): Platform
    {
        /**
         * @var array<string, string> $connectionData
         */
        $connectionData = $this->deploymentConfig->get(
            ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT,
            []
        );

        return Platform::fromArray([
            'name' => 'magento2',
            'version' => $this->productMetadata->getVersion(),
            'integration_version' => $this->moduleResource->getDbVersion('Sequra_Core'),
            'uname' => php_uname(),
            'db_name' => !empty($connectionData['model']) ? $connectionData['model'] : 'mysql',
            'db_version' => $this->sqlVersionProvider->getSqlVersion(),
            'php_version' => PHP_VERSION,
        ]);
    }

    /**
     * Creates an instance of OrderStatistics from magento Order.
     *
     * @param array $orderInfo
     * @phpstan-param array<string, string|float|int> $orderInfo
     *
     * @return OrderStatistics
     */
    private function createOrderStatistics(array $orderInfo): OrderStatistics
    {
        $amount = $orderInfo['total_paid'] ?? $orderInfo['total_due'] ?? 0;

        return OrderStatistics::fromArray([
            'completed_at' => $orderInfo['created_at'] ?? '',
            'currency' => $orderInfo['order_currency_code'] ?? '',
            'amount' => TransformEntityService::transformPrice((float) $amount),
            'merchant_reference' => [
                'order_ref_1' => $orderInfo['increment_id'] ?? ''
            ],
            'payment_method' => $orderInfo['method'] ?? '',
            'country' => $orderInfo['country_id'] ?? '',
            'raw_status' => $orderInfo['status'] ?? ''
        ]);
    }
}
