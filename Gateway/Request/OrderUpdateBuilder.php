<?php

namespace Sequra\Core\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Class OrderUpdateBuilder
 *
 * @package Sequra\Core\Gateway\Request
 */
class OrderUpdateBuilder implements BuilderInterface
{

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
