<?php

namespace Sequra\Core\Model\Api;

use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Tracking\Tracking;
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
     * The tracking list last sent in this request, by order entity id.
     *
     * @var array<string, mixed[]>
     */
    private $sentTrackings = [];

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
     * when it matches what seQura was last told. A list already sent for the order in this request is not sent
     * again, since saving a shipment with several tracking records commits each of them. A failure is logged and
     * never stops the shipment from saving.
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
            $trackings = $this->trackingBuilder->build($order);
            $entityId = $order->getEntityId();
            $orderKey = is_scalar($entityId) ? (string)$entityId : '';
            $batch = Tracking::toBatchArray($trackings);
            if (isset($this->sentTrackings[$orderKey]) && $this->sentTrackings[$orderKey] === $batch) {
                return;
            }

            $this->orderUpdater->update(
                (string)$order->getStoreId(),
                new OrderUpdateRequest((string)$order->getIncrementId(), null, null, null, null, $trackings)
            );
            $this->sentTrackings[$orderKey] = $batch;
        } catch (Throwable $e) {
            Logger::logError('Order synchronization for shipment tracking failed. ' . $e->getMessage(), 'Integration');
        }
    }
}
