<?php

namespace Sequra\Core\Services\BusinessLogic\OrderCleanup;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use SeQura\Core\BusinessLogic\Domain\OrderCleanup\Services\OrderCleanupService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Throwable;

/**
 * Schedules the first nightly cleanup of stale solicitations, keeping an existing schedule.
 *
 * The first run is the next day at 02:00 in the shop's timezone plus up to an hour, so shops sharing the same
 * install time do not all clean up at once. Setup schedules it, and every task runner tick schedules it again when
 * setup could not, so a failed attempt never leaves the cleanup off for good.
 */
class OrderCleanupScheduler
{
    /**
     * Hour of the day the first cleanup runs at, in the shop's timezone.
     */
    private const FIRST_RUN_HOUR = 2;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(TimezoneInterface $timezone)
    {
        $this->timezone = $timezone;
    }

    /**
     * Schedules the first run unless the cleanup is already scheduled. A failure is logged and never raised.
     *
     * @return void
     */
    public function ensureScheduled(): void
    {
        try {
            /** @var OrderCleanupService $cleanupService */
            $cleanupService = ServiceRegister::getService(OrderCleanupService::class);
            $cleanupService->schedule($this->getFirstRunTimestamp());
        } catch (Throwable $e) {
            Logger::logError('Scheduling the order cleanup failed because: ' . $e->getMessage());
        }
    }

    /**
     * Returns the timestamp of tomorrow at the first run hour in the shop's timezone, plus a random minute.
     *
     * @return int
     */
    private function getFirstRunTimestamp(): int
    {
        return $this->timezone->date()
            ->modify('+1 day')
            ->setTime(self::FIRST_RUN_HOUR, random_int(0, 59))
            ->getTimestamp();
    }
}
