<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\OrderCreationInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
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
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $shopOrderRepository
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $shopOrderRepository,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->cartManagement = $cartManagement;
        $this->shopOrderRepository = $shopOrderRepository;
        $this->quoteRepository = $quoteRepository;
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

        if (!$quote->getIsActive()) {
            $quote->setIsActive(true);
            $this->quoteRepository->save($quote);
        }
    }
}
