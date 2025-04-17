<?php

namespace Sequra\Core\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;

class TransactionIdHandler implements HandlerInterface
{
    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @phpstan-param array<string, mixed> $handlingSubject
     * @phpstan-param array<string, mixed> $response
     *
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $handlingSubject['payment'];
        // TODO: Cannot call method getPayment() on mixed
        // @phpstan-ignore-next-line
        if ($paymentDO->getPayment() instanceof Payment) {
            /**
             * TODO: Cannot call method getPayment() on mixed
             * @var Payment $orderPayment
             * @phpstan-ignore-next-line
             **/
            $orderPayment = $paymentDO->getPayment();
            /** @var SeQuraOrder $sequraOrder */
            $sequraOrder = $response['data'];
            $orderPayment->setTransactionId(date('is') . "-" . $sequraOrder->getOrderRef1());
            $orderPayment->setIsTransactionClosed($this->shouldCloseTransaction());
            $closed = $this->shouldCloseParentTransaction($orderPayment);
            $orderPayment->setShouldCloseParentTransaction($closed);
        }
    }

    /**
     * Whether transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseTransaction()
    {
        return false;
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
        return false;
    }
}
