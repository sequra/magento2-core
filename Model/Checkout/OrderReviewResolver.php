<?php

namespace Sequra\Core\Model\Checkout;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class OrderReviewResolver
 *
 * Decides whether an order was paid with seQura and is still on hold for manual review.
 */
class OrderReviewResolver
{
    /**
     * Whether the order is a seQura order whose payment is on hold.
     *
     * A webhook with the needs_review state moves the order to the payment review status before the
     * shopper is redirected back to the shop, so the status alone tells an order on hold apart.
     *
     * @param OrderInterface|null $order
     *
     * @return bool
     */
    public function isInReview(?OrderInterface $order): bool
    {
        if ($order === null || !$order->getEntityId()) {
            return false;
        }

        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== ConfigProvider::CODE) {
            return false;
        }

        return $order->getStatus() === Order::STATE_PAYMENT_REVIEW;
    }
}
