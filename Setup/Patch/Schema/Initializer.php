<?php

namespace Sequra\Core\Setup\Patch\Schema;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Sequra\Core\Setup\DatabaseHandler;

class Initializer implements SchemaPatchInterface
{
    /**
     * @var DatabaseHandler
     */
    protected $databaseHandler;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->databaseHandler = new DatabaseHandler($moduleDataSetup);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $this->removeObsoleteColumns();
        return $this;
    }

    /**
     * Removes obsolete columns from database tables
     *
     * @return void
     */
    private function removeObsoleteColumns()
    {
        $this->databaseHandler->removeColumn('sales_order', 'sequra_order_send');
        $this->databaseHandler->removeColumn('quote', 'sequra_is_remote_sale');
        $this->databaseHandler->removeColumn('quote', 'sequra_operator_ref');
        $this->databaseHandler->removeColumn('quote', 'sequra_remote_sale');
    }
}
