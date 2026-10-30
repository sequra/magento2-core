<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\OrderRequestStates;
use Sequra\Core\Setup\DatabaseHandler;

/**
 * Puts a seQura order on hold the way a needs_review webhook does.
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
        $data['state'] = OrderRequestStates::ON_HOLD;
        $this->conn->getConnection()->update(
            DatabaseHandler::SEQURA_ORDER_TABLE,
            ['data' => json_encode($data)],
            ['index_3 = ?' => $orderId]
        );

        $objectManager = ObjectManager::getInstance();
        /**
         * @var Order $order
         */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId($orderId);
        if (!$order->getId()) {
            $this->httpErrorResponse('Order not found', 404);
        }
        $order->addCommentToStatusHistory('Put on hold by the seQura helper', Order::STATE_PAYMENT_REVIEW);
        /**
         * @var OrderRepositoryInterface $orderRepository
         */
        $orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $orderRepository->save($order);

        return ['success' => true, 'data' => ['cart_id' => (string) ($data['cartId'] ?? '')]];
    }
}
