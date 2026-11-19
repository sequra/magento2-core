<?php

namespace Sequra\Core\Services\BusinessLogic;

use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Exceptions\InvalidOrderStateException;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\CreateOrderRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use SeQura\Core\BusinessLogic\Webhook\Exceptions\OrderNotFoundException;
use SeQura\Core\BusinessLogic\Webhook\Services\ShopOrderService;
use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService as SeQuraOrderService;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class OrderService implements ShopOrderService
{
    /**
     * @var SeQuraOrderRepositoryInterface
     */
    private $seQuraOrderRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchOrderCriteriaBuilder;
    /**
     * @var OrderCollectionFactory
     */
    private $collectionFactory;
    /**
     * @var OrderRepositoryInterface
     */
    private $shopOrderRepository;
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * @var SeQuraOrderService
     */
    private $sequraOrderService;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var CreateOrderRequestBuilderFactory
     */
    private $createOrderRequestBuilderFactory;

    /**
     * Constructor for OrderService
     *
     * @param SearchCriteriaBuilder $searchOrderCriteriaBuilder
     * @param OrderCollectionFactory $collectionFactory
     * @param OrderRepositoryInterface $shopOrderRepository
     * @param OrderManagementInterface $orderManagement
     * @param SeQuraOrderRepositoryInterface $seQuraOrderRepository
     * @param SeQuraTranslationProvider $translationProvider
     * @param CartRepositoryInterface $cartProvider
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     * @param SeQuraOrderService $sequraOrderService
     */
    public function __construct(
        SearchCriteriaBuilder $searchOrderCriteriaBuilder,
        OrderCollectionFactory $collectionFactory,
        OrderRepositoryInterface $shopOrderRepository,
        OrderManagementInterface $orderManagement,
        SeQuraOrderRepositoryInterface $seQuraOrderRepository,
        SeQuraTranslationProvider $translationProvider,
        CartRepositoryInterface $cartProvider,
        CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory,
        SequraOrderService $sequraOrderService
    ) {
        $this->searchOrderCriteriaBuilder = $searchOrderCriteriaBuilder;
        $this->collectionFactory = $collectionFactory;
        $this->shopOrderRepository = $shopOrderRepository;
        $this->orderManagement = $orderManagement;
        $this->seQuraOrderRepository = $seQuraOrderRepository;
        $this->translationProvider = $translationProvider;
        $this->cartRepository = $cartProvider;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
        $this->sequraOrderService = $sequraOrderService;
    }

    /**
     * Gets the order IDs for the report.
     *
     * @param int $page The page number.
     * @param int $limit The number of order IDs to retrieve.
     *
     * @return array<int> The order IDs.
     */
    public function getReportOrderIds(int $page, int $limit = 5000): array
    {
        return [];
    }

    /**
     * Get the order IDs for statistics.
     *
     * @param int $page The page number.
     * @param int $limit The number of order IDs to retrieve.
     *
     * @return array<int> The order IDs.
     */
    public function getStatisticsOrderIds(int $page, int $limit = 5000): array
    {
        $toDate = new DateTime();
        $fromDate = clone $toDate;
        $fromDate->modify('-7 days');

        $collection = $this->collectionFactory
            ->create()
            ->setPage($page, $limit)
            ->addFieldToFilter('created_at', ['gteq' => $fromDate->format('Y-m-d H:i:s')])
            ->addFieldToFilter('created_at', ['lteq' => $toDate->format('Y-m-d H:i:s')]);

        $count = $collection->getSize();
        if ($page !== 1 && $page > round($count / $limit)) {
            return [];
        }

        return array_column($collection->getData(), 'entity_id');
    }

    /**
     * Updates the order status based on the webhook data.
     *
     * @param Webhook $webhook
     * @param string $status
     * @param int|null $reasonCode
     * @param string|null $message
     *
     * @throws Exception
     */
    public function updateStatus(
        Webhook $webhook,
        string $status,
        ?int $reasonCode = null,
        ?string $message = null
    ): void {
        switch ($status) {
            case Order::STATE_PENDING_PAYMENT:
            case Order::STATE_PAYMENT_REVIEW:
                $this->updateOrderToStatus($webhook, $status);
                break;
            case Order::STATE_CANCELED:
                $this->cancelOrder($webhook);
                break;
        }
    }

    /**
     * Get the order URL.
     *
     * @param string $merchantReference
     *
     * @return string
     */
    public function getOrderUrl(string $merchantReference): string
    {
        return '';
    }

    /**
     * Get the order reference.
     *
     * @param string $orderReference
     *
     * @return CreateOrderRequest
     *
     * @throws NoSuchEntityException
     */
    public function getCreateOrderRequest(string $orderReference): CreateOrderRequest
    {
        $seQuraOrder = $this->seQuraOrderRepository->getByOrderReference($orderReference);
        if (!$seQuraOrder) {
            throw new NoSuchEntityException();
        }
        $quote = $this->cartRepository->get((int)$seQuraOrder->getCartId());

        $builder = $this->createOrderRequestBuilderFactory->create([
            'cartId' => $quote->getId(),
            // TODO: Call to an undefined method Magento\Quote\Api\Data\CartInterface::getStore()
            // @phpstan-ignore-next-line
            'storeId' => (string)$quote->getStore()->getId(),
        ]);

        return $builder->build();
    }

    /**
     * Updates the Magento order and SeQuraOrder statuses.
     *
     * @param Webhook $webhook
     * @param string $status
     *
     * @return void
     *
     * @throws Exception
     */
    private function updateOrderToStatus(Webhook $webhook, string $status): void
    {
        $order = $this->getOrder($webhook);
        if ($order) {
            $orderReference = $order->getIncrementId();
            $this->sequraOrderService->updateSeQuraOrderStatus($webhook);
        } else {
            $orderReference = $this->sequraOrderService->createOrder($webhook);
            $order = $this->getOrderByIncrementId($orderReference);
        }

        if (!$order) {
            return;
        }

        $order->addCommentToStatusHistory($this->translationProvider->translate(
            'sequra.orderRefSent',
            $orderReference
        ), $status);
        $this->shopOrderRepository->save($order);
    }

    /**
     * Cancels the order in Magento and updates the SeQuraOrder status.
     *
     * @param Webhook $webhook
     *
     * @return void
     *
     * @throws InvalidOrderStateException
     * @throws OrderNotFoundException
     */
    private function cancelOrder(Webhook $webhook): void
    {
        $order = $this->getOrder($webhook);
        if (!$order) {
            throw new OrderNotFoundException("Magento order with reference {$webhook->getOrderRef1()} not found.", 404);
        }

        $this->sequraOrderService->updateSeQuraOrderStatus($webhook);
        /**
         * @var int $orderId
         */
        $orderId = $order->getId();
        if ($order->canUnhold()) {
            $this->orderManagement->unHold($orderId);
        }

        $this->orderManagement->cancel($orderId);
    }

    /**
     * Gets the magento order.
     *
     * @param Webhook $webhook
     *
     * @return Order|null
     *
     * @throws OrderNotFoundException
     */
    private function getOrder(Webhook $webhook): ?Order
    {
        $orderRef1 = $this->sequraOrderService->getOrderReference1($webhook);
        if (empty($orderRef1)) {
            return null;
        }

        return $this->getOrderByIncrementId($orderRef1);
    }

    /**
     * Get the order by increment ID.
     *
     * @param string $orderIncrementId
     *
     * @return Order|null
     */
    protected function getOrderByIncrementId(string $orderIncrementId): ?Order
    {
        $searchCriteria = $this->searchOrderCriteriaBuilder
            ->addFilter('increment_id', $orderIncrementId)
            ->create();
        $orderList = $this->shopOrderRepository->getList($searchCriteria)->getItems();

        /**
         * @var Order|null $order
         */
        $order = array_pop($orderList);
        return $order;
    }
}
