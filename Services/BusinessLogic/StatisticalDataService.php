<?php

namespace Sequra\Core\Services\BusinessLogic;

use SeQura\Core\BusinessLogic\Domain\SendReport\Models\SendReport;
use SeQura\Core\BusinessLogic\Domain\StatisticalData\Models\StatisticalData;
use SeQura\Core\BusinessLogic\Domain\StatisticalData\Services\StatisticalDataService as CoreStatisticalDataService;

/**
 * Class StatisticalDataService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class StatisticalDataService extends CoreStatisticalDataService
{
    private const SCHEDULE_TIME = '4 am';
    private const SCHEDULE_TIME_NEXT_DAY = '4 am +1 day';

    /**
     * @inheirtDoc
     */
    public function saveStatisticalData(StatisticalData $statisticalData): void
    {
        $this->statisticalDataRepository->setStatisticalData($statisticalData);

        if (!$statisticalData->isSendStatisticalData()) {
            return;
        }

        if ($this->timeProvider->getCurrentLocalTime()->getTimestamp() <= strtotime(self::SCHEDULE_TIME)) {
            $sendReport = new SendReport(strtotime(self::SCHEDULE_TIME));
        } else {
            $sendReport = new SendReport(strtotime(self::SCHEDULE_TIME_NEXT_DAY));
        }

        $this->sendReportRepository->setSendReport(
            $sendReport
        );
    }

    /**
     * @inheirtDoc
     */
    public function setSendReportTime(): void
    {
        $time = strtotime(self::SCHEDULE_TIME_NEXT_DAY);
        $this->sendReportRepository->setSendReport(new SendReport($time));
    }
}
