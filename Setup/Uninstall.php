<?php

namespace Sequra\Core\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    /**
     * Removes plugin database tables.
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->dropEntityTable(DatabaseHandler::SEQURA_ENTITY_TABLE);
        $databaseHandler->dropEntityTable(DatabaseHandler::SEQURA_QUEUE_TABLE);
        $databaseHandler->dropEntityTable(DatabaseHandler::SEQURA_ORDER_TABLE);

        $installer->endSetup();
    }
}
