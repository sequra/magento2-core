<?php

namespace Sequra\Core\Model\Api;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\QueueService;
use Sequra\Core\Model\Api\Builders\TrackingBuilder;
use Sequra\Core\Model\Api\Exception\OrderUpdateFailedException;
use Sequra\Core\Model\Api\Tasks\TrackingSynchronizationTask;
use Sequra\Core\Model\Ui\ConfigProvider;
use Throwable;

/**
 * Sends the tracking records of a seQura order's shipments to seQura.
 *
 * The update goes through the integration-core task queue, one queue per order, so saving a shipment never waits
 * for seQura, a failed update is retried, and the updates of one order are sent in the order they were made.
 */
class TrackingSynchronizer
{
    /**
     * Prefix of the queue the order's synchronization tasks run in, followed by the order ID.
     */
    public const QUEUE_NAME_PREFIX = 'shipment-tracking-';

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
     * Orders whose synchronization waits for the transaction to commit.
     *
     * @var array<int, true>
     */
    private $pendingOrders = [];

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
     * Queues the order's synchronization once the transaction that saved or deleted the record commits.
     *
     * The record is a shipment or a tracking record. A shipment saved with its tracking records fires one event per
     * record. They all register a callback, and the first one to run queues the order for all of them, so the order
     * is queued once per transaction.
     *
     * @param AbstractModel $object The shipment or tracking record that was saved or deleted.
     * @param int $orderId
     *
     * @return void
     */
    public function synchronizeAfterCommit(AbstractModel $object, int $orderId): void
    {
        $this->pendingOrders[$orderId] = true;

        $object->getResource()->addCommitCallback(function () use ($orderId): void {
            if (!isset($this->pendingOrders[$orderId])) {
                return;
            }

            unset($this->pendingOrders[$orderId]);
            $this->enqueue($orderId);
        });
    }

    /**
     * Queues the synchronization of the order, or nothing when the order was not paid with seQura.
     *
     * The payment is checked before anything else, so shipments and tracking records of other orders cost one
     * query. A failure is logged and never stops the shipment from saving.
     *
     * @param int $orderId
     *
     * @return void
     */
    public function enqueue(int $orderId): void
    {
        try {
            if (!$this->isPaidWithSeQura($orderId)) {
                return;
            }

            $this->getQueueService()->enqueue(
                self::QUEUE_NAME_PREFIX . $orderId,
                new TrackingSynchronizationTask($orderId)
            );
        } catch (Throwable $e) {
            Logger::logError(
                'Queueing the order synchronization for shipment tracking failed. ' . $e->getMessage(),
                'Integration'
            );
        }
    }

    /**
     * Sends the current tracking list of the order.
     *
     * The whole list is sent every time, an empty one included, and integration-core skips the call to seQura when
     * it matches what seQura was last told. Runs from the task queue, which retries it when it fails.
     *
     * @param int $orderId
     *
     * @return void
     *
     * @throws OrderUpdateFailedException|\Exception When seQura or integration-core did not accept the update.
     */
    public function synchronize(int $orderId): void
    {
        $order = $this->findOrder($orderId);
        if ($order === null || !$this->isKnownToSeQura($order)) {
            return;
        }

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
    }

    /**
     * Loads the order, or returns null when it no longer exists.
     *
     * @param int $orderId
     *
     * @return Order|null
     */
    private function findOrder(int $orderId): ?Order
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $order instanceof Order ? $order : null;
    }

    /**
     * Tells whether integration-core has the seQura order of the order, which an update needs.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isKnownToSeQura(Order $order): bool
    {
        /** @var SeQuraOrderRepositoryInterface $repository */
        $repository = ServiceRegister::getService(SeQuraOrderRepositoryInterface::class);

        return $repository->getByShopReference((string)$order->getIncrementId()) !== null;
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

    /**
     * Returns the integration-core queue service.
     *
     * @return QueueService
     */
    private function getQueueService(): QueueService
    {
        /** @var QueueService $queueService */
        $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);

        return $queueService;
    }
}
