<?php

namespace Sequra\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use Sequra\Core\Model\Api\TrackingSynchronizer;

/**
 * Sends the order's tracking list to seQura once a shipment or one of its tracking records is saved or deleted.
 *
 * The synchronization runs after the transaction commits, so a shipment saved together with its tracking records is
 * synchronized once, after all of them are stored, whether it was created in the admin or through the REST API.
 */
class ShipmentTrackingObserver implements ObserverInterface
{
    /**
     * @var TrackingSynchronizer
     */
    private $trackingSynchronizer;

    /**
     * @param TrackingSynchronizer $trackingSynchronizer
     */
    public function __construct(TrackingSynchronizer $trackingSynchronizer)
    {
        $this->trackingSynchronizer = $trackingSynchronizer;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        if (WebhookController::isWebhookProcessing()) {
            return;
        }

        $object = $observer->getData('data_object');
        if (!$object instanceof Shipment && !$object instanceof Track) {
            return;
        }

        $orderId = $object->getOrderId();
        if (is_numeric($orderId)) {
            $this->trackingSynchronizer->synchronizeAfterCommit($object, (int)$orderId);
        }
    }
}
