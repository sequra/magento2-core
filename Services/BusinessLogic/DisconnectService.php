<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\App\ResourceConnection;
use SeQura\Core\BusinessLogic\Domain\Integration\Disconnect\DisconnectServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class DisconnectService implements DisconnectServiceInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->removeTableData('sequra_entity', 'index_1');
        $this->removeTableData('sequra_order', 'index_2');
        $this->removeTableData('sequra_queue', 'index_1');
    }

    /**
     * Removes all table data for provided context.
     *
     * @param string $tableName
     * @param string $contextColumn
     *
     * @return void
     */
    private function removeTableData(string $tableName, string $contextColumn): void
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName($tableName);
        // TODO: Possible raw SQL statement "delete from $tableName where $contextColumn = " detected
        // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
        $sql = "delete from $tableName where $contextColumn = " . StoreContext::getInstance()->getStoreId();
        $connection->query($sql);
    }
}
