<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\Domain\Order\OrderStates;
use SeQura\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use SeQura\Core\BusinessLogic\Webhook\Services\ShopOrderService;
use SeQura\Core\Infrastructure\ServiceRegister;

/**
 * Puts a seQura order on hold through the same order update a needs_review webhook runs.
 *
 * The sandbox resolves the review of its test shoppers within seconds, before the shopper is back
 * in the shop, so the on hold state has to be set by hand to reach the confirmation page with it.
 */
class PutOrderOnHoldTask extends Task
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
        if (!isset($args['order_id'])) {
            $this->httpErrorResponse('Missing required argument: order_id', 400);
        }

        $orderId = (string) $args['order_id'];
        $data = $this->getSeQuraOrderData($orderId);
        $reference = $data['reference'] ?? '';
        $webhook = Webhook::fromArray([
            'order_ref' => is_string($reference) ? $reference : '',
            'order_ref_1' => $orderId,
            'sq_state' => OrderStates::STATE_NEEDS_REVIEW,
        ]);

        /**
         * @var ShopOrderService $shopOrderService
         */
        $shopOrderService = ServiceRegister::getService(ShopOrderService::class);
        $shopOrderService->updateStatus($webhook, Order::STATE_PAYMENT_REVIEW);

        return ['success' => true, 'data' => ['cart_id' => (string) ($data['cartId'] ?? '')]];
    }
}
