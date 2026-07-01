<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\OrderCreationInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use SeQura\Core\BusinessLogic\Webhook\Exceptions\OrderNotFoundException;

class OrderCreation implements OrderCreationInterface
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;
    /**
     * @var OrderRepositoryInterface
     */
    private $shopOrderRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $shopOrderRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $shopOrderRepository,
        CartRepositoryInterface $quoteRepository,
        OrderFactory $orderFactory
    ) {
        $this->cartManagement = $cartManagement;
        $this->shopOrderRepository = $shopOrderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Creates shop order and returns shop order reference.
     *
     * @param string $cartId
     *
     * @return string
     *
     * @throws OrderNotFoundException
     * @throws CouldNotSaveException
     */
    public function createOrder(string $cartId): string
    {
        // Idempotency for webhook redeliveries: once a quote is placed it gets a reserved order id
        // and is deactivated, so re-placing it would fail (placeOrder resolves via getActive()).
        // If an order already exists for this quote, return it instead of re-placing.
        $existingReference = $this->placedOrderReference((int)$cartId);
        if ($existingReference !== null) {
            return $existingReference;
        }

        // The Express Checkout product flow keeps its temporary quote inactive between solicits so
        // it never shadows the shopper's real cart; placeOrder requires an active quote, so
        // reactivate it here. A regular checkout / cart already-active quote is left untouched.
        $this->activateCart((int)$cartId);

        /** @var null|Order $order */
        $order = $this->getOrderById(
            $this->cartManagement->placeOrder((int)$cartId)
        );

        if (!$order) {
            throw new OrderNotFoundException("Magento order with cart id {$cartId} not found.", 404);
        }

        return $order->getIncrementId();
    }

    /**
     * Returns the increment id of the order already placed from this quote, or null when unplaced.
     *
     * @param int $cartId
     *
     * @return string|null
     */
    private function placedOrderReference(int $cartId): ?string
    {
        try {
            $quote = $this->quoteRepository->get($cartId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        $reservedOrderId = (string)$quote->getReservedOrderId();
        if ($reservedOrderId === '') {
            return null;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($reservedOrderId);

        return $order->getId() ? (string)$order->getIncrementId() : null;
    }

    /**
     * Returns the Magento order by id.
     *
     * @param int $orderId
     *
     * @return Order|null
     */
    protected function getOrderById(int $orderId): ?Order
    {
        /** @var null|Order $order */
        $order = $this->shopOrderRepository->get($orderId);

        return $order;
    }

    /**
     * Reactivates the quote when it is inactive so it can be placed as an order.
     *
     * @param int $cartId
     *
     * @return void
     */
    private function activateCart(int $cartId): void
    {
        try {
            $quote = $this->quoteRepository->get($cartId);
        } catch (NoSuchEntityException $e) {
            return;
        }

        // Only an unplaced Express draft may be reactivated. A reserved order id is set the moment a
        // quote goes through placeOrder, so a non-empty value means this quote has already been
        // placed (regular checkout, HPP, or an earlier webhook delivery). Reactivating such a quote
        // would defeat Magento's cross-request "already placed" guard — placeOrder resolves the cart
        // via getActive(), which rejects an inactive placed quote — and let a webhook replay place a
        // duplicate order.
        if ((string)$quote->getReservedOrderId() !== '') {
            return;
        }

        if (!$quote->getIsActive()) {
            $quote->setIsActive(true);
            $this->quoteRepository->save($quote);
        }
    }
}
