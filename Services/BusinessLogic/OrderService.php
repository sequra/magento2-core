<?php

namespace Sequra\Core\Services\BusinessLogic;

use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Exceptions\InvalidOrderStateException;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\CreateOrderRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\MerchantReference;
use SeQura\Core\BusinessLogic\Domain\Order\Models\PaymentMethod;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\OrderRequestStatusMapping;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use SeQura\Core\BusinessLogic\Webhook\Exceptions\OrderNotFoundException;
use SeQura\Core\BusinessLogic\Webhook\Services\ShopOrderService;
use Magento\Sales\Model\Order;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ServiceRegister;

/**
 * Class OrderService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
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

    public function __construct(
        SearchCriteriaBuilder          $searchOrderCriteriaBuilder,
        OrderRepositoryInterface       $shopOrderRepository,
        OrderManagementInterface       $orderManagement,
        CartManagementInterface        $cartManagement,
        SeQuraOrderRepositoryInterface $seQuraOrderRepository
    )
    {
        $this->searchOrderCriteriaBuilder = $searchOrderCriteriaBuilder;
        $this->shopOrderRepository = $shopOrderRepository;
        $this->orderManagement = $orderManagement;
        $this->cartManagement = $cartManagement;
        $this->seQuraOrderRepository = $seQuraOrderRepository;
    }

    /**
     * @inheritdoc
     */
    public function getReportOrderIds(int $page, int $limit = 5000): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStatisticsOrderIds(int $page, int $limit = 5000): array
    {
        $toDate = new DateTime();
        $fromDate = clone $toDate;
        $fromDate->modify('-7 days');

        $searchCriteria = $this->searchOrderCriteriaBuilder
            ->addFilter('created_at', $fromDate->format('Y-m-d H:i:s'), 'gteq')
            ->addFilter('created_at', $toDate->format('Y-m-d H:i:s'), 'lteq')
            ->setCurrentPage($page + 1)
            ->setPageSize($limit)
            ->create();

        $orderList = $this->shopOrderRepository->getList($searchCriteria);
        if ($page * $limit > $orderList->getTotalCount()) {
            return [];
        }

        return array_column($orderList->getData(), 'entity_id');
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function updateStatus(Webhook $webhook, string $status): void
    {
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
        $order->addCommentToStatusHistory(__('Order ref sent to SeQura: %1', $order->getIncrementId()), $status);
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

        if ($order->canUnhold()) {
            $this->orderManagement->unHold($order->getId());
        }

        $this->orderManagement->cancel($order->getId());
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
            $this->cartManagement->placeOrder($seQuraOrder->getCartId())
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
            $this->getOrderPaymentMethodInfo($updatedSeQuraOrder->getMerchant()->getId(), $webhook->getProductCode())
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

    private function getOrder(Webhook $webhook): ?Order
    {
        if (empty($webhook->getOrderRef1())) {
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
     * @param string $orderIncrementId
     * @return Order|null
     */
    protected function getOrderByIncrementId(string $orderIncrementId): ?Order
    {
        $searchCriteria = $this->searchOrderCriteriaBuilder
            ->addFilter('increment_id', $orderIncrementId)
            ->create();
        $orderList = $this->shopOrderRepository->getList($searchCriteria)->getItems();

        return array_pop($orderList);
    }

    /**
     * @param int $orderId
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
     * @param string $merchantId
     * @param string $paymentMethodId
     *
     * @return PaymentMethod|null
     *
     * @throws HttpRequestException
     */
    private function getOrderPaymentMethodInfo(string $merchantId, string $paymentMethodId): ?PaymentMethod
    {
        $paymentMethods = $this->getPaymentMethodsService()->getMerchantsPaymentMethods($merchantId);

        foreach ($paymentMethods as $method) {
            if ($method->getProduct() === $paymentMethodId) {
                return new PaymentMethod($paymentMethodId, $method->getTitle(), $method->getIcon());
            }
        }

        return null;
    }

    /**
     * Gets an instance of PaymentMethodsService.
     *
     * @return PaymentMethodsService
     */
    private function getPaymentMethodsService(): PaymentMethodsService
    {
        if (!isset($this->paymentMethodsService)) {
            $this->paymentMethodsService = ServiceRegister::getService(PaymentMethodsService::class);
        }

        return $this->paymentMethodsService;
    }
}
