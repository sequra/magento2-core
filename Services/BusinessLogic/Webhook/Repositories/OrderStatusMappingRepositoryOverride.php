<?php

namespace Sequra\Core\Services\BusinessLogic\Webhook\Repositories;

use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\Domain\Order\OrderStates;
use SeQura\Core\BusinessLogic\Domain\OrderStatusSettings\Models\OrderStatusMapping;
use SeQura\Core\BusinessLogic\Domain\OrderStatusSettings\RepositoryContracts\OrderStatusSettingsRepositoryInterface;

class OrderStatusMappingRepositoryOverride implements OrderStatusSettingsRepositoryInterface
{

    public function getOrderStatusMapping(): array
    {
        return [
            new OrderStatusMapping(OrderStates::STATE_APPROVED, Order::STATE_PENDING_PAYMENT),
            new OrderStatusMapping(OrderStates::STATE_NEEDS_REVIEW, Order::STATE_PAYMENT_REVIEW),
            new OrderStatusMapping(OrderStates::STATE_CANCELLED, Order::STATE_CANCELED),
        ];
    }

    public function setOrderStatusMapping(array $orderStatusMapping): void
    {
        // Intentionally left blank. Magento has strict order status transition therefore merchants do not set order map.
    }
}
