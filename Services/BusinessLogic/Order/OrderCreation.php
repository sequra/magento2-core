<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\Exception\CouldNotSaveException;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\OrderCreationInterface;
use Magento\Quote\Api\CartManagementInterface;
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
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $shopOrderRepository
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $shopOrderRepository
    ) {
        $this->cartManagement = $cartManagement;
        $this->shopOrderRepository = $shopOrderRepository;
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
}
