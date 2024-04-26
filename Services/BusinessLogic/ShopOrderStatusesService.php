<?php

namespace Sequra\Core\Services\BusinessLogic;

use SeQura\Core\BusinessLogic\Domain\Integration\ShopOrderStatuses\ShopOrderStatusesServiceInterface;

class ShopOrderStatusesService implements ShopOrderStatusesServiceInterface
{

    /**
     * @inheritDoc
     */
    public function getShopOrderStatuses(): array
    {
        return [];
    }
}