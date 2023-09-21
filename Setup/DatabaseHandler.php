<?php

namespace Sequra\Core\Setup;

use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Class DatabaseHandler
 *
 * @package Sequra\Core\Setup
 */
class DatabaseHandler
{
    public const SEQURA_ENTITY_TABLE = 'sequra_entity';
    public const SEQURA_QUEUE_TABLE = 'sequra_queue';
    public const SEQURA_ORDER_TABLE = 'sequra_order';

    /**
     * @var ModuleDataSetupInterface
     */
    private $installer;

    public function __construct(ModuleDataSetupInterface $installer)
    {
        $this->installer = $installer;
    }

    /**
     * @return ModuleDataSetupInterface
     */
    public function getInstaller(): ModuleDataSetupInterface
    {
        return $this->installer;
    }

    /**
     * Drops Sequra entity table.
     *
     * @param string $tableName Name of the table.
     */
    public function dropEntityTable(string $tableName): void
    {
        $tableInstance = $this->installer->getTable($tableName);
        if ($this->installer->getConnection()->isTableExists($tableInstance)) {
            $this->installer->getConnection()->dropTable($tableInstance);
        }
    }

    /**
     * @param string $tableName
     * @param string $columnName
     *
     * @return void
     */
    public function removeColumn(string $tableName, string $columnName)
    {
        $tableInstance = $this->installer->getTable($tableName);
        if ($this->installer->getConnection()->isTableExists($tableInstance) &&
            $this->installer->getConnection()->tableColumnExists($tableName, $columnName)) {
            $this->installer->getConnection()->dropColumn($tableName, $columnName);
        }
    }
}
