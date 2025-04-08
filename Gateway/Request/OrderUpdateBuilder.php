<?php

namespace Sequra\Core\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Payment;

class OrderUpdateBuilder implements BuilderInterface
{

    /**
     * Builds the request data for order updates
     *
     * @param array $buildSubject The data provided for the builder
     * @return array The request data array
     */
    public function build(array $buildSubject): array
    {
        $order = null;

        if (isset($buildSubject['payment'])) {
            $paymentDO = $buildSubject['payment'];
            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();
            $order = $payment->getOrder();
        }

        return [
            'order' => $order,
        ];
    }
}
