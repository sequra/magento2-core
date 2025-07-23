<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Sequra\Core\Setup\DatabaseHandler;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Task class
 */
class VerifyOrderHasMerchantIdTask extends Task
{

    /**
     * Check if dummy merchant configuration is in use
     *
     * @param bool $widgets
     */
    private function isDummyConfigInUse(bool $widgets): bool
    {
        $expected_rows = $widgets ? 2 : 1;
        $table_name = DatabaseHandler::SEQURA_ENTITY_TABLE;
        $query      = "SELECT * FROM $table_name 
        WHERE (`type` = 'ConnectionData' 
        AND `data` LIKE '%\"username\":\"dummy_automated_tests\"%') 
        OR (`type` = 'WidgetSettings' AND `data` LIKE '%\"displayOnProductPage\":true%')";
        $result     = $this->conn->getConnection()->fetchAll($query);
        return is_array($result) && count($result) === $expected_rows;
    }

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
        if (! isset($args['order_id']) || ! isset($args['merchant_id'])) {
            $this->httpErrorResponse('Missing required arguments: order_id OR merchant_id', 400);
        }

        $orderId = $args['order_id'];
        $merchantId = $args['merchant_id'];

        $table_name = DatabaseHandler::SEQURA_ORDER_TABLE;
        $query      = "SELECT `data` FROM $table_name WHERE `index_3` = '$orderId' LIMIT 1";
        $result     = $this->conn->getConnection()->fetchAll($query);
        if (!isset($result[0]['data'])) {
            $this->httpErrorResponse('Order not found', 404);
        }
        $data = json_decode($result[0]['data'], true);
        if (!isset($data['merchant']['id'])) {
            $this->httpErrorResponse('Merchant ID not found in order data', 404);
        }
        $currentMerchantId = $data['merchant']['id'];
        if ($currentMerchantId !== $merchantId) {
            $this->httpErrorResponse("Merchant ID '$currentMerchantId' does not match '$merchantId'", 400);
        }
        return $this->httpSuccessResponse();
    }
}
