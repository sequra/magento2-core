<?php

namespace Sequra\Core\Plugin;

use Magento\Sales\Model\Order;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class OrderCanCancelPlugin
 *
 * A SeQura order in payment review cannot be cancelled until SeQura decides.
 *
 * Refusing it in canCancel() keeps Order::cancel() from running at all, so no stock is returned and the
 * admin does not show the Cancel button. The cancellation that SeQura itself sends through the webhook
 * is let through.
 */
class OrderCanCancelPlugin
{
    /**
     * Returns false for a SeQura order in payment review.
     *
     * @param Order $subject
     * @param bool $result
     *
     * @return bool
     */
    public function afterCanCancel(Order $subject, bool $result): bool
    {
        if (!$result || WebhookController::isWebhookProcessing()) {
            return $result;
        }

        if ($subject->getStatus() !== Order::STATE_PAYMENT_REVIEW) {
            return $result;
        }

        $payment = $subject->getPayment();

        return !$payment || $payment->getMethod() !== ConfigProvider::CODE;
    }
}
