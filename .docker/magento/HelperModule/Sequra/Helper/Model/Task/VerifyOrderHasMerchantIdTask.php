<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Task class
 */
class VerifyOrderHasMerchantIdTask extends Task
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
        if (! isset($args['order_id']) || ! isset($args['merchant_id'])) {
            $this->httpErrorResponse('Missing required arguments: order_id OR merchant_id', 400);
        }

        $orderId = $args['order_id'];
        $merchantId = $args['merchant_id'];

        $data = $this->getSeQuraOrderData((string) $orderId);
        $merchant = $data['merchant'] ?? null;
        if (!is_array($merchant) || !isset($merchant['id'])) {
            $this->httpErrorResponse('Merchant ID not found in order data', 404);
        }
        /**
         * @var string $currentMerchantId
         */
        $currentMerchantId = $merchant['id'];
        if ($currentMerchantId !== $merchantId) {
            $this->httpErrorResponse("Merchant ID '$currentMerchantId' does not match '$merchantId'", 400);
        }
        return $this->httpSuccessResponse();
    }
}
