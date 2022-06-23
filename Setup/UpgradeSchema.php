<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
class UpgradeSchema implements  UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup,
                            ModuleContextInterface $context){
        $setup->startSetup();
        if (version_compare($context->getVersion(), '2.4.0') < 0) {
            // Get module table
            $tableQuote = $setup->getTable('quote');
            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableQuote) == true) {
                $setup->getConnection()->addColumn(
                    $tableQuote,
                    'sequra_remote_sale',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                        'nullable' => true,
                        'default' => 0,
                        'comment' => 'Is payment form sent by SMS?'
                    ]
                );
                $setup->getConnection()->addColumn(
                    $tableQuote,
                    'sequra_operator_ref',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length' => 64,
                        'nullable' => true,
                        'comment' => 'Operator ref for SeQura?'
                    ]
                );
            }
        }
        if (version_compare($context->getVersion(), '2.4.1.0') < 0) {
            $data = [];
            $statuses = [
                'partially_refunded_sequra' => __('Partially refunded with SeQura'),
                'refunded_sequra' => __('Refunded with SeQura'),
            ];
            foreach ($statuses as $code => $info) {
                $data[] = ['status' => $code, 'label' => $info];
            }
            $setup->getConnection()
                ->insertOnDuplicate($setup->getTable('sales_order_status'), $data, ['status', 'label']);
        }
        $setup->endSetup();
    }
}
