<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use SeQura\Core\BusinessLogic\DataAccess\SendReport\Entities\SendReport as SendReportEntity;
use SeQura\Core\BusinessLogic\Domain\SendReport\Models\SendReport;
use SeQura\Core\BusinessLogic\DataAccess\StatisticalData\Entities\StatisticalData as StatisticalDataEntity;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use SeQura\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use DateTime;

/**
 * Class Version254
 *
 * @package Sequra\Core\Setup\Patch\Data
 */
class Version254 implements DataPatchInterface
{
    private const SCHEDULE_TIME = '4 am';
    private const SCHEDULE_TIME_NEXT_DAY = '4 am +1 day';

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return $this|Version254
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    public function apply(): Version254
    {
        $statisticalDataArray = $this->getStatisticalEntitiesForAllStores();

        foreach ($statisticalDataArray as $statisticalData) {
            $storeId = $statisticalData->getStoreId();

            if (
                $this->getSendReportForGivenStore($storeId) ||
                !$statisticalData->getStatisticalData()->isSendStatisticalData()
            ) {
                return $this;
            }

            $this->saveSendReport($storeId);
        }

        return $this;
    }

    /**
     * @return StatisticalDataEntity[]
     *
     * @throws RepositoryNotRegisteredException
     */
    private function getStatisticalEntitiesForAllStores(): array
    {
        $statisticalDataRepository = RepositoryRegistry::getRepository(StatisticalDataEntity::getClassName());

        return $statisticalDataRepository->select();
    }

    /**
     * @param string $storeId
     *
     * @return SendReportEntity|null
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    private function getSendReportForGivenStore(string $storeId): ?SendReportEntity
    {
        $queryFilter = new QueryFilter();
        $queryFilter->where('context', Operators::EQUALS, $storeId);

        /** @var SendReportEntity|null $sendReport $sendReport */
        $sendReport = $this->getSendReportRepository()->selectOne($queryFilter);

        return $sendReport;
    }

    /**
     * @throws RepositoryNotRegisteredException
     */
    private function saveSendReport(string $storeId): void
    {
        $sendReport = new SendReport(strtotime(self::SCHEDULE_TIME_NEXT_DAY));

        if ((new DateTime())->getTimestamp() <= strtotime(self::SCHEDULE_TIME)) {
            $sendReport = new SendReport(strtotime(self::SCHEDULE_TIME));
        }

        $sendReportEntity = new SendReportEntity();
        $sendReportEntity->setContext($storeId);
        $sendReportEntity->setSendReportTime($sendReport->getSendReportTime());
        $sendReportEntity->setSendReport($sendReport);
        $this->getSendReportRepository()->save($sendReportEntity);
    }

    /**
     * @return RepositoryInterface
     *
     * @throws RepositoryNotRegisteredException
     */
    private function getSendReportRepository(): RepositoryInterface
    {
        return RepositoryRegistry::getRepository(SendReportEntity::getClassName());
    }
}
