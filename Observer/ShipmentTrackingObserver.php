<?php

namespace Sequra\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Sequra\Core\Model\Api\TrackingSynchronizer;

/**
 * Sends the order's tracking list to seQura once a shipment or one of its tracking records is saved or deleted.
 *
 * Bound to the commit events, so a shipment saved together with its tracking records is synchronized only after
 * all of them are stored, whether it was created in the admin or through the REST API.
 */
class ShipmentTrackingObserver implements ObserverInterface
{
    /**
     * @var TrackingSynchronizer
     */
    private $trackingSynchronizer;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param TrackingSynchronizer $trackingSynchronizer
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(TrackingSynchronizer $trackingSynchronizer, OrderRepositoryInterface $orderRepository)
    {
        $this->trackingSynchronizer = $trackingSynchronizer;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $order = $this->getOrder($observer->getData('data_object'));

        if ($order !== null) {
            $this->trackingSynchronizer->synchronize($order);
        }
    }

    /**
     * Returns the order of the saved or deleted shipment or tracking record.
     *
     * @param mixed $object
     *
     * @return Order|null
     */
    private function getOrder($object): ?Order
    {
        if ($object instanceof Shipment) {
            return $object->getOrder();
        }

        if (!$object instanceof Track || !is_numeric($object->getOrderId())) {
            return null;
        }

        try {
            $order = $this->orderRepository->get((int)$object->getOrderId());
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $order instanceof Order ? $order : null;
    }
}
