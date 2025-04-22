<?php

namespace Sequra\Core\Setup;

use Magento\Framework\DB\Ddl\Table;

class DatabaseHandler
{
    public const SEQURA_ENTITY_TABLE = 'sequra_entity';
    public const SEQURA_QUEUE_TABLE = 'sequra_queue';
    public const SEQURA_ORDER_TABLE = 'sequra_order';

    /**
     * @var \Magento\Framework\Setup\SetupInterface
     */
    private $installer;

    /**
     * Constructor for DatabaseHandler
     *
     * @param \Magento\Framework\Setup\SetupInterface $installer The installer
     */
    public function __construct($installer)
    {
        $this->installer = $installer;
    }

    /**
     * Get the installer instance
     *
     * @return \Magento\Framework\Setup\SetupInterface The installer instance
     */
    public function getInstaller()
    {
        return $this->installer;
    }

    /**
     * Creates entity table in the database
     *
     * @param string $tableName Name of the table to create
     *
     * @return void
     */
    public function createEntityTable(string $tableName): void
    {
        $entityTable = $this->installer->getTable($tableName);

        if (!$this->installer->getConnection()->isTableExists($entityTable)) {
            $entityTable = $this->installer->getConnection()
                ->newTable($this->installer->getTable($tableName))
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'Id'
                )
                ->addColumn(
                    'type',
                    Table::TYPE_TEXT,
                    128,
                    ['nullable' => false],
                    'Type'
                )
                ->addColumn(
                    'index_1',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index1'
                )
                ->addColumn(
                    'index_2',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index2'
                )
                ->addColumn(
                    'index_3',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index3'
                )
                ->addColumn(
                    'index_4',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index4'
                )
                ->addColumn(
                    'index_5',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index5'
                )
                ->addColumn(
                    'index_6',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index6'
                )
                ->addColumn(
                    'index_7',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index7'
                )
                ->addColumn(
                    'index_8',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index8'
                )
                ->addColumn(
                    'index_9',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index9'
                )
                ->addColumn(
                    'data',
                    Table::TYPE_TEXT,
                    Table::MAX_TEXT_SIZE,
                    ['nullable' => false],
                    'Data'
                );

            $this->installer->getConnection()->createTable($entityTable);
        }
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
     * Removes a column from a table if it exists.
     *
     * @param string $tableName The name of the table
     * @param string $columnName The name of the column to remove
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
