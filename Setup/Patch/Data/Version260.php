<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Sequra\Core\Block\Widget\Teaser;
use Sequra\Core\Model\Config\Source\WidgetPaymentMethods;

/**
 * Class Version260
 *
 * Migration script to transition from the widgets based on the PageBuilder block
 * to the new widgets based on the Teaser block.
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
                        'instance_type' => Teaser::class,
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
            foreach ($this->widgetPaymentMethods->getPaymentMethodValues() as $value) {
                if ($value['product'] === $paymentMethod) {
                    $paymentMethodsParams[] = $this->widgetPaymentMethods->encodePaymentMethodValue($value);
                    break;
                }
            }
        }
        return $paymentMethodsParams;
    }
}
