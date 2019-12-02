<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * install tables
     *
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     * @return void
     */
    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        //Add fields to order
        $tableSalesOrder = $setup->getTable('sales_order');
        $setup->getConnection()->addColumn(
            $tableSalesOrder,
            'sequra_order_send',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                'nullable' => true,
                'default' => 0,
                'comment' => 'Do we need to inform this order\'s shipments to SeQura?'
            ]
        );

        $data = [];
        $statuses = [
            'pending_sequra' => __('Pending Sequra'),
            'approved_sequra' => __('Approved in Sequra')
        ];
        foreach ($statuses as $code => $info) {
            $data[] = ['status' => $code, 'label' => $info];
        }
        $setup->getConnection()
            ->insertArray($setup->getTable('sales_order_status'), ['status', 'label'], $data);
        $setup->endSetup();
    }
}