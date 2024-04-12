<?php

namespace Sequra\Core\Services\BusinessLogic;

use SeQura\Core\BusinessLogic\Domain\StatisticalData\Models\StatisticalData;
use SeQura\Core\BusinessLogic\Domain\StatisticalData\Services\StatisticalDataService as CoreStatisticalDataService;
use SeQura\Core\BusinessLogic\Domain\Stores\Services\StoreService;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\Utility\TimeProvider;

/**
 * Class StatisticalDataService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class StatisticalDataService extends CoreStatisticalDataService
{
    private const SCHEDULE_TIME = '4 am';

    /**
     * @inheirtDoc
     */
    public function saveStatisticalData(StatisticalData $statisticalData): void
    {
        $this->statisticalDataRepository->setStatisticalData($statisticalData);
    }

    /**
     * @inheirtDoc
     */
    public function getContextsForSendingReport(): array
    {
        if ($this->timeProvider->getCurrentLocalTime()->getTimestamp()
            !== strtotime(self::SCHEDULE_TIME)) {
            return [];
        }

        return $this->getStoreService()->getConnectedStores();
    }

    /**
     * @return StoreService
     */
    private function getStoreService(): StoreService
    {
        return ServiceRegister::getService(StoreService::class);
    }
}
