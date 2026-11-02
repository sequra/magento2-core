<?php

namespace Sequra\Core\Plugin;

use Magento\Sales\Model\Order;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class OrderCanCancelPlugin
 *
 * Refuses canCancel() for a SeQura order in payment review, unless a SeQura webhook is being processed.
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
