<?php

namespace Sequra\Core\Services\BusinessLogic;

use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Exceptions\InvalidOrderStateException;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\CreateOrderRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\MerchantReference;
use SeQura\Core\BusinessLogic\Domain\Order\Models\PaymentMethod;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\OrderRequestStatusMapping;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use SeQura\Core\BusinessLogic\Webhook\Exceptions\OrderNotFoundException;
use SeQura\Core\BusinessLogic\Webhook\Services\ShopOrderService;
use Magento\Sales\Model\Order;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService as SeQuraOrderService;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class OrderService implements ShopOrderService
{
    /**
     * Product codes for installment payments category.
     */
    private const INSTALLMENT_METHOD_CODES = ['pp3', 'pp6', 'pp9'];

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
     * @var CartManagementInterface
     */
    private $cartManagement;
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;
    /**
     * @var SeQuraOrderService|null
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
     * @param CartManagementInterface $cartManagement
     * @param SeQuraOrderRepositoryInterface $seQuraOrderRepository
     * @param SeQuraTranslationProvider $translationProvider
     * @param CartRepositoryInterface $cartProvider
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     */
    public function __construct(
        SearchCriteriaBuilder            $searchOrderCriteriaBuilder,
        OrderCollectionFactory           $collectionFactory,
        OrderRepositoryInterface         $shopOrderRepository,
        OrderManagementInterface         $orderManagement,
        CartManagementInterface          $cartManagement,
        SeQuraOrderRepositoryInterface   $seQuraOrderRepository,
        SeQuraTranslationProvider        $translationProvider,
        CartRepositoryInterface          $cartProvider,
        CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
    ) {
        $this->searchOrderCriteriaBuilder = $searchOrderCriteriaBuilder;
        $this->collectionFactory = $collectionFactory;
        $this->shopOrderRepository = $shopOrderRepository;
        $this->orderManagement = $orderManagement;
        $this->cartManagement = $cartManagement;
        $this->seQuraOrderRepository = $seQuraOrderRepository;
        $this->translationProvider = $translationProvider;
        $this->cartRepository = $cartProvider;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
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
     * @throws NoSuchEntityException
     */
    public function getCreateOrderRequest(string $orderReference): CreateOrderRequest
    {
        $seQuraOrder = $this->seQuraOrderRepository->getByOrderReference($orderReference);
        if (!$seQuraOrder) {
            throw new NoSuchEntityException();
        }
        $quote = $this->cartRepository->get((int) $seQuraOrder->getCartId());

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
        $order ? $this->updateSeQuraOrderStatus($webhook) : $order = $this->createOrder($webhook);
        $order->addCommentToStatusHistory($this->translationProvider->translate(
            'sequra.orderRefSent',
            $order->getIncrementId()
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

        $this->updateSeQuraOrderStatus($webhook);
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
     * Creates and saves a new SeQuraOrder.
     *
     * @param Webhook $webhook
     *
     * @return Order
     *
     * @throws Exception
     */
    private function createOrder(Webhook $webhook): Order
    {
        $seQuraOrder = $this->getSeQuraOrder($webhook->getOrderRef());

        /** @var Order $order */
        $order = $this->getOrderById(
            $this->cartManagement->placeOrder((int) $seQuraOrder->getCartId())
        );

        $updatedSeQuraOrder = (new CreateOrderRequest(
            OrderRequestStatusMapping::mapOrderRequestStatus($webhook->getSqState()),
            $seQuraOrder->getMerchant(),
            $seQuraOrder->getUnshippedCart(),
            $seQuraOrder->getDeliveryMethod(),
            $seQuraOrder->getCustomer(),
            $seQuraOrder->getPlatform(),
            $seQuraOrder->getDeliveryAddress(),
            $seQuraOrder->getInvoiceAddress(),
            $seQuraOrder->getGui(),
            MerchantReference::fromArray([
                'order_ref_1' => $order->getIncrementId(),
                'order_ref_2' => $webhook->getOrderRef()
            ])
        ))->toSequraOrderInstance($webhook->getOrderRef());

        $updatedSeQuraOrder->setPaymentMethod(
            $this->getOrderPaymentMethodInfo($updatedSeQuraOrder->getReference(), $webhook->getProductCode())
        );

        // Update order with merchant order references so that core can update order state with all required data
        $this->seQuraOrderRepository->setSeQuraOrder($updatedSeQuraOrder);

        return $order;
    }

    /**
     * Updates the SeQuraOrder status.
     *
     * @param Webhook $webhook
     *
     * @return void
     *
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    private function updateSeQuraOrderStatus(Webhook $webhook): void
    {
        $seQuraOrder = $this->getSeQuraOrder($webhook->getOrderRef());
        $seQuraOrder->setState(OrderRequestStatusMapping::mapOrderRequestStatus($webhook->getSqState()));
        $this->seQuraOrderRepository->setSeQuraOrder($seQuraOrder);
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
        $orderIncrementId = $webhook->getOrderRef1();
        if (empty($orderIncrementId)) {
            $seQuraOrder = $this->getSeQuraOrder($webhook->getOrderRef());
            $orderIncrementId = $seQuraOrder->getMerchantReference()->getOrderRef1();
        }

        if (empty($orderIncrementId)) {
            return null;
        }

        return $this->getOrderByIncrementId($webhook->getOrderRef1());
    }

    /**
     * Gets the SeQura order.
     *
     * @param string $orderReference
     *
     * @return SeQuraOrder
     *
     * @throws OrderNotFoundException
     */
    private function getSeQuraOrder(string $orderReference): SeQuraOrder
    {
        $seQuraOrder = $this->seQuraOrderRepository->getByOrderReference($orderReference);
        if (!$seQuraOrder) {
            throw new OrderNotFoundException("SeQura order with reference $orderReference is not found.", 404);
        }

        return $seQuraOrder;
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

    /**
     * Get the order by ID.
     *
     * @param int $orderId
     *
     * @return Order|null
     */
    protected function getOrderById(int $orderId): ?Order
    {
        /** @var Order $order */
        $order = $this->shopOrderRepository->get($orderId);

        return $order;
    }

    /**
     * Returns PaymentMethod information for SeQura order.
     *
     * @param string $orderReference
     * @param string $paymentMethodId
     *
     * @return PaymentMethod|null
     *
     * @throws HttpRequestException
     */
    private function getOrderPaymentMethodInfo(string $orderReference, string $paymentMethodId): ?PaymentMethod
    {
        $methodCategories = $this->getSeQuraOrderService()->getAvailablePaymentMethodsInCategories($orderReference);
        foreach ($methodCategories as $category) {
            foreach ($category->getMethods() as $method) {
                if ($method->getProduct() === $paymentMethodId) {
                    $name = in_array($paymentMethodId, self::INSTALLMENT_METHOD_CODES) ?
                        $category->getTitle() :
                        $method->getTitle();

                    /**
                     * TODO: Parameter #3 $icon of class PaymentMethod constructor expects string, string|null given
                     * @var string $icon
                     */
                    $icon = $method->getIcon() ?? '';
                    return new PaymentMethod($paymentMethodId, $name, $icon);
                }
            }
        }

        return null;
    }

    /**
     * Returns an instance of Order service.
     *
     * @return SeQuraOrderService
     */
    private function getSeQuraOrderService(): SeQuraOrderService
    {
        if (!isset($this->sequraOrderService)) {
            $this->sequraOrderService = ServiceRegister::getService(SeQuraOrderService::class);
        }

        return $this->sequraOrderService;
    }
}
