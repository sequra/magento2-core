<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Interceptor as MagentoOrder;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Cart as SeQuraCart;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;

/**
 * Class OrderCancellationObserver
 *
 * @package Sequra\Core\Observer
 */
class OrderCancellationObserver implements ObserverInterface
{
    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $orderData = $observer->getData('order');
        if (WebhookController::isWebhookProcessing() || $orderData->getStatus() !== Order::STATE_CANCELED) {
            return;
        }

        try {
            $this->handleCancellation($orderData);
        } catch (Exception $e) {
            $this->handleCancellationError($e);
        }
    }

    /**
     * Handles order cancellation.
     *
     * @param MagentoOrder $orderData
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleCancellation(MagentoOrder $orderData): void
    {
        $statusHistory = $orderData->getAllStatusHistory();
        if ($statusHistory && $statusHistory[count($statusHistory) - 2]->getStatus() === Order::STATE_PAYMENT_REVIEW) {
            throw new LocalizedException(__('Order with "payment review" status cannot be cancelled.'));
        }

        StoreContext::doWithStore($orderData->getStoreId(), [$this->getOrderService(), 'updateOrder'], [
            new OrderUpdateData(
                $orderData->getIncrementId(),
                new SeQuraCart($orderData->getOrderCurrencyCode()),
                new SeQuraCart($orderData->getOrderCurrencyCode()),
                null, null
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
     * Handles the order cancellation errors.
     *
     * @param Exception $e
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function handleCancellationError(Exception $e): void
    {
        Logger::logError('Order synchronization for cancellation failed. ' . $e->getMessage(), 'Integration');

        if ($e instanceof LocalizedException) {
            throw $e;
        }
    }
}
