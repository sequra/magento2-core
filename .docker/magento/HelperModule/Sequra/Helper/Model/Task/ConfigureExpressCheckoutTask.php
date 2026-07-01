<?php
/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Sequra\Core\Setup\DatabaseHandler;

/**
 * Enables Express Checkout on every storefront surface for the dummy test store.
 *
 * Complements ConfigureDummyTask, which sets up the connection/credentials/payment methods but
 * leaves Express Checkout off. Availability additionally requires an ExpressCheckoutSettings row
 * with the relevant page enabled.
 */
class ConfigureExpressCheckoutTask extends Task
{
    /**
     * Execute the task
     *
     * @param string[] $args Arguments for the task
     *
     * @return array<string, mixed>
     *
     * @throws \Exception If the task fails
     */
    public function execute(array $args = [])
    {
        if (!$this->isExpressCheckoutConfigured()) {
            $this->enableExpressCheckout();
        }

        return $this->httpSuccessResponse();
    }

    /**
     * Whether an ExpressCheckoutSettings row already exists for the store.
     */
    private function isExpressCheckoutConfigured(): bool
    {
        $table = DatabaseHandler::SEQURA_ENTITY_TABLE;
        $id = $this->conn->getConnection()->fetchOne(
            "SELECT id FROM $table WHERE `type` = 'ExpressCheckoutSettings' AND `index_1` = '1'"
        );

        return !empty($id);
    }

    /**
     * Inserts an ExpressCheckoutSettings row enabling the product, cart and mini-cart surfaces.
     */
    private function enableExpressCheckout(): void
    {
        $table = DatabaseHandler::SEQURA_ENTITY_TABLE;
        $conn = $this->conn->getConnection();
        $id = max((int) $conn->fetchOne("SELECT MAX(id) FROM $table"), 0) + 1;

        $data = '{"class_name":"SeQura\\\\Core\\\\BusinessLogic\\\\DataAccess\\\\ExpressCheckout\\\\Entities\\\\ExpressCheckoutSettings",'
            . '"id":' . $id . ',"storeId":"1",'
            . '"expressCheckoutSettings":{"expressCheckoutConfigs":['
            . '{"page":"product","enabled":true},'
            . '{"page":"cart","enabled":true},'
            . '{"page":"mini-cart","enabled":true}'
            . ']}}';

        $conn->insert(
            $table,
            [
                'id'      => $id,
                'type'    => 'ExpressCheckoutSettings',
                'index_1' => '1',
                'data'    => $data,
            ]
        );
    }
}
