<?php

namespace Sequra\Core\Model\Api;

use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Model\Api\Builders\TrackingBuilder;
use Sequra\Core\Model\Ui\ConfigProvider;
use Throwable;

/**
 * Sends the tracking records of a seQura order's shipments to seQura.
 */
class TrackingSynchronizer
{
    /**
     * @var TrackingBuilder
     */
    private $trackingBuilder;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @param TrackingBuilder $trackingBuilder
     * @param OrderUpdater $orderUpdater
     */
    public function __construct(TrackingBuilder $trackingBuilder, OrderUpdater $orderUpdater)
    {
        $this->trackingBuilder = $trackingBuilder;
        $this->orderUpdater = $orderUpdater;
    }

    /**
     * Sends the current tracking list of the order, or nothing when the order was not paid with seQura.
     *
     * The whole list is sent every time, an empty one included, and integration-core skips the call to seQura
     * when it matches what seQura was last told. A failure is logged and never stops the shipment from saving.
     *
     * @param Order $order
     *
     * @return void
     */
    public function synchronize(Order $order): void
    {
        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() !== ConfigProvider::CODE) {
            return;
        }

        try {
            $this->orderUpdater->update(
                (string)$order->getStoreId(),
                new OrderUpdateRequest(
                    (string)$order->getIncrementId(),
                    null,
                    null,
                    null,
                    null,
                    $this->trackingBuilder->build($order)
                )
            );
        } catch (Throwable $e) {
            Logger::logError('Order synchronization for shipment tracking failed. ' . $e->getMessage(), 'Integration');
        }
    }
}
