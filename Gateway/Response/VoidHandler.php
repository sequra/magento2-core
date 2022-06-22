<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sequra\Core\Gateway\Response;

use Magento\Sales\Model\Order\Payment;

class VoidHandler extends TransactionIdHandler
{
    /**
     * @param Payment $orderPayment
     * @param \Redsys\Transaction $transaction
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function setTransactionId(Payment $orderPayment, \Sequra\Transaction $transaction)
    {
        return;
    }

    /**
     * Whether transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseTransaction()
    {
        return true;
    }

    /**
     * Whether parent transaction should be closed
     *
     * @param Payment $orderPayment
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function shouldCloseParentTransaction(Payment $orderPayment)
    {
        return true;
    }
}
