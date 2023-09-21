<?php

namespace Sequra\Core\Services\BusinessLogic\Webhook\Repositories;

use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\Domain\Order\OrderStates;
use SeQura\Core\BusinessLogic\Webhook\Repositories\OrderStatusMappingRepository;

/**
 * Class OrderStatusMappingRepositoryOverride
 *
 * @package Sequra\Core\Services\BusinessLogic\Webhook\Repositories
 */
class OrderStatusMappingRepositoryOverride implements OrderStatusMappingRepository
{

    public function getOrderStatusMapping(): array
    {
        return [
            OrderStates::STATE_APPROVED => Order::STATE_PENDING_PAYMENT,
            OrderStates::STATE_NEEDS_REVIEW => Order::STATE_PAYMENT_REVIEW,
            OrderStates::STATE_CANCELLED => Order::STATE_CANCELED,
        ];
    }

    public function setOrderStatusMapping(array $orderStatusMapping): void
    {
        // Intentionally left blank. Magento has strict order status transition therefore merchants do not set order map.
    }
}
