<?php

namespace Sequra\Core\Gateway\Response;

use Magento\Sales\Model\Order\Payment;

class RefundHandler extends VoidHandler
{
    /**
     * Whether parent transaction should be closed
     *
     * @param Payment $orderPayment
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function shouldCloseParentTransaction(Payment $orderPayment)
    {
        // TODO: Cannot call method canRefund() on Magento\Sales\Model\Order\Invoice|null
        // TODO: Cannot call method getInvoice() on Magento\Sales\Model\Order\Creditmemo|null
        // @phpstan-ignore-next-line
        return !(bool)$orderPayment->getCreditmemo()->getInvoice()->canRefund();
    }
}
