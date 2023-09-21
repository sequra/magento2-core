<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo as MagentoRefund;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\Utility\TransformEntityService;

/**
 * Class OrderReturnObserver
 *
 * @package Sequra\Core\Observer
 */
class OrderRefundObserver implements ObserverInterface
{
    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $refundData = $observer->getData('data_object');

        try {
            $this->handleRefund($refundData);
        } catch (Exception $e) {
            $this->handleRefundError($e);
        }
    }

    /**
     * Handles the refund event.
     *
     * @param MagentoRefund $refundData
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleRefund(MagentoRefund $refundData): void
    {
        $orderData = $refundData->getOrder();

        if($orderData->getStatus() === Order::STATE_PAYMENT_REVIEW) {
            throw new LocalizedException(__('Order with "payment review" status cannot be refunded.'));
        }

        $shippedCart = TransformEntityService::transformOrderCartToSeQuraCart($orderData, true);
        $unshippedCart = TransformEntityService::transformOrderCartToSeQuraCart($orderData, false);

        StoreContext::doWithStore($refundData->getStoreId(), [$this->getOrderService(), 'updateOrder'], [
            new OrderUpdateData(
                $orderData->getIncrementId(),
                $shippedCart,
                $unshippedCart,
                null,
                null
            )
        ]);
    }

    /**
     * Returns an instance of Order service.
     *
     * @return OrderService
     */
    private function getOrderService(): OrderService
    {
        if (!isset($this->orderService)) {
            $this->orderService = ServiceRegister::getService(OrderService::class);
        }

        return $this->orderService;
    }

    /**
     * Handles the order refund errors.
     *
     * @param Exception $e
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function handleRefundError(Exception $e): void
    {
        Logger::logError('Order synchronization for refund failed. ' . $e->getMessage(), 'Integration');

        if ($e instanceof LocalizedException) {
            throw $e;
        }
    }
}
