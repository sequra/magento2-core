<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Random\RandomException;
use SeQura\Core\BusinessLogic\Domain\OrderCleanup\Services\OrderCleanupService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\Bootstrap;
use Throwable;

/**
 * Schedules the first nightly cleanup of stale solicitations.
 *
 * The first run is the next day at 02:00 in the shop's timezone plus up to an hour, so shops sharing
 * the same install time do not all clean up at once. An existing schedule is kept.
 */
class ScheduleOrderCleanup implements DataPatchInterface
{
    /**
     * Hour of the day the first cleanup runs at, in the shop's timezone.
     */
    private const FIRST_RUN_HOUR = 2;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param TimezoneInterface $timezone
     * @param Bootstrap $bootstrap
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        TimezoneInterface $timezone,
        Bootstrap $bootstrap
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->timezone = $timezone;

        $bootstrap->initInstance();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [Initializer::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        try {
            /** @var OrderCleanupService $cleanupService */
            $cleanupService = ServiceRegister::getService(OrderCleanupService::class);
            $cleanupService->schedule($this->getFirstRunTimestamp());

            Logger::logInfo('Migration ' . self::class . ' has been successfully finished.');
        } catch (Throwable $e) {
            Logger::logError('Scheduling the order cleanup failed because: ' . $e->getMessage());
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * Returns the timestamp of tomorrow at the first run hour in the shop's timezone, plus up to an hour.
     *
     * @return int
     *
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    private function getFirstRunTimestamp(): int
    {
        return $this->timezone->date()
            ->modify('+1 day')
            ->setTime(self::FIRST_RUN_HOUR, random_int(0, 59), random_int(0, 59))
            ->getTimestamp();
    }
}
