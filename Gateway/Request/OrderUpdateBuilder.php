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
     * @phpstan-param array<string, Payment> $buildSubject
     *
     * @return array<string, mixed> The request data array
     */
    public function build(array $buildSubject): array
    {
        $order = null;

        if (isset($buildSubject['payment'])) {
            $paymentDO = $buildSubject['payment'];
            /**
             * TODO: Call to an undefined method Magento\Sales\Model\Order\Payment::getPayment().
             * @var Payment $payment
             * @phpstan-ignore-next-line
             */
            $payment = $paymentDO->getPayment();
            $order = $payment->getOrder();
        }

        return [
            'order' => $order,
        ];
    }
}
