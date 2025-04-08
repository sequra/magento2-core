<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Sequra\Core\Model\Config\Source\WidgetPaymentMethods;

/**
 * Class Version260
 *
 * Migration script to transition from the widgets based on the PageBuilder block to the new widgets based on the Teaser block.
 *
 */
class Version260 implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;
    
    /**
     * @var WidgetPaymentMethods
     */
    private WidgetPaymentMethods $widgetPaymentMethods;

    /**
     * Constructor for Version260 data patch
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param WidgetPaymentMethods $widgetPaymentMethods
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup, WidgetPaymentMethods $widgetPaymentMethods)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->widgetPaymentMethods = $widgetPaymentMethods;
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
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        $cms_block = $this->moduleDataSetup->getTable('cms_block');
        $cms_block_store = $this->moduleDataSetup->getTable('cms_block_store');
        $widget_instance = $this->moduleDataSetup->getTable('widget_instance');

       // Steps:
       // 1. Read all rows from cms_block table having content like %data-content-type="sequra_core"%
       // 2. For each row:
       //   2.1. Save payment_method in a variable by reading the content of the block and getting data-payment-method="..." values if any. Otherwise use pp3 as the value
       //   2.2. Save the title in a variable.
       //   2.3. Query the widget_instance table to get every row containing widget_parameters = {"block_id":"$title"}
       //   2.4. For each row:
       //     2.4.1. Update the value of the column instance_type to Sequra\Core\Block\Widget\Teaser
       //     2.4.2. Update the value of the column widget_parameters
       //   2.2 Remove the row from cms_block and from cms_block_store tables

        $query = $connection->select()
            ->from($cms_block)
            ->where('content LIKE ?', '%data-content-type="sequra_core"%');

        $cmsBlocks = $connection->fetchAll($query);

        foreach ($cmsBlocks as $cmsBlock) {
            $blockId = $cmsBlock['block_id'];
            $title = $cmsBlock['title'];
            $content = $cmsBlock['content'];
            preg_match('/data-payment-method="([^"]+)"/', $content, $matches);
            $paymentMethods = isset($matches[1]) ? explode(',', $matches[1]) : ['pp3'];

            $query = $connection->select()
                ->from($widget_instance)
                ->where('widget_parameters = ?', '{"block_id":"'.$title.'"}');

            $widgetInstances = $connection->fetchAll($query);
            foreach ($widgetInstances as $widgetInstance) {
                $connection->update(
                    $widget_instance,
                    [
                        'instance_type' => 'Sequra\Core\Block\Widget\Teaser',
                        'widget_parameters' => json_encode(
                            [
                                'price_sel' => '.product-info-main .price',
                                'dest_sel' => '',
                                'theme' => '',
                                'payment_methods' => $this->getPaymentMethodsParams($paymentMethods)
                            ]
                        )
                    ],
                    ['instance_id = ?' => $widgetInstance['instance_id']]
                );
            }
            
            // Remove the PageBuilder block from the database
            $connection->delete($cms_block_store, ['block_id = ?' => $blockId]);
            $connection->delete($cms_block, ['block_id = ?' => $blockId]);
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * Get the payment methods parameters
     *
     * @param string[] $paymentMethods
     * @return string[]
     */
    private function getPaymentMethodsParams(array $paymentMethods): array
    {
        $paymentMethodsParams = [];
        foreach ($paymentMethods as $paymentMethod) {
            foreach ($this->widgetPaymentMethods->getPaymentMethodValues() as $paymentMethodValue) {
                if ($paymentMethodValue['product'] === $paymentMethod) {
                    $paymentMethodsParams[] = $this->widgetPaymentMethods->encodePaymentMethodValue($paymentMethodValue);
                    break;
                }
            }
        }
        return $paymentMethodsParams;
    }
}
