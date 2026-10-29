<?php

namespace Sequra\Core\Model\Api;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
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
     * @param TrackingBuilder $trackingBuilder
     * @param OrderUpdater $orderUpdater
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        TrackingBuilder $trackingBuilder,
        OrderUpdater $orderUpdater,
        OrderRepositoryInterface $orderRepository,
        OrderPaymentRepositoryInterface $paymentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->trackingBuilder = $trackingBuilder;
        $this->orderUpdater = $orderUpdater;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Sends the current tracking list of the order, or nothing when the order was not paid with seQura.
     *
     * The payment is checked before the order is loaded, so shipments and tracking records of other orders cost
     * one query. The whole list is sent every time, an empty one included, and integration-core skips the call to
     * seQura when it matches what seQura was last told. A failure is logged and never stops the shipment from saving.
     *
     * @param int $orderId
     *
     * @return void
     */
    public function synchronize(int $orderId): void
    {
        if (!$this->isPaidWithSeQura($orderId)) {
            return;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            return;
        }

        if (!$order instanceof Order) {
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
