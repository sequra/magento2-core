<?php

namespace Sequra\Core\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Sequra\Core\Model\Api\TrackingSynchronizer;
use Sequra\Core\Model\Ui\ConfigProvider;

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
     * @var OrderPaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param TrackingSynchronizer $trackingSynchronizer
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        TrackingSynchronizer $trackingSynchronizer,
        OrderRepositoryInterface $orderRepository,
        OrderPaymentRepositoryInterface $paymentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->trackingSynchronizer = $trackingSynchronizer;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
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
     * A tracking record's order is loaded only when it was paid with seQura, so a shop pushing tracking numbers
     * for other orders through the REST API does not load each of them.
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

        $orderId = (int)$object->getOrderId();
        if (!$this->isPaidWithSeQura($orderId)) {
            return null;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $order instanceof Order ? $order : null;
    }

    /**
     * Tells whether the order was paid with seQura, reading only its payment.
     *
     * @param int $orderId
     *
     * @return bool
     */
    private function isPaidWithSeQura(int $orderId): bool
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderPaymentInterface::PARENT_ID, $orderId)
            ->create();

        foreach ($this->paymentRepository->getList($searchCriteria)->getItems() as $payment) {
            if ($payment->getMethod() === ConfigProvider::CODE) {
                return true;
            }
        }

        return false;
    }
}
