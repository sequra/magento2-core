<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\Exception\CouldNotSaveException;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\OrderCreationInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

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
     * Returns shop order reference.
     *
     * @param string $idReference
     *
     * @return string
     * @throws CouldNotSaveException
     */
    public function getShopOrderReference(string $idReference): string
    {
        /** @var Order $order */
        $order = $this->getOrderById(
            $this->cartManagement->placeOrder((int)$idReference)
        );

        return $order->getIncrementId();
    }

    /**
     * Get the Magento order by id.
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
}
