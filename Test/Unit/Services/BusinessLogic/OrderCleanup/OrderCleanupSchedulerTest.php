<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic\OrderCleanup;

use DateTime;
use DateTimeZone;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\Domain\OrderCleanup\Services\OrderCleanupService;
use SeQura\Core\Infrastructure\Configuration\Configuration;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\Logger\LoggerConfiguration;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\Utility\TimeProvider;
use Sequra\Core\Services\BusinessLogic\OrderCleanup\OrderCleanupScheduler;

/**
 * Class OrderCleanupSchedulerTest
 *
 * That the first cleanup run is scheduled for the next night in the shop's timezone, and that a failure to schedule
 * it is logged instead of raised.
 */
class OrderCleanupSchedulerTest extends TestCase
{
    /**
     * @var MockObject&OrderCleanupService
     */
    private $cleanupService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cleanupService = $this->createMock(OrderCleanupService::class);
        $cleanupService = $this->cleanupService;
        ServiceRegister::registerService(OrderCleanupService::class, static function () use ($cleanupService) {
            return $cleanupService;
        });
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Logger::resetInstance();
        LoggerConfiguration::resetInstance();
    }

    /**
     * The first run is the next day between 02:00 and 02:59 in the shop's timezone, and an existing schedule is kept.
     *
     * @return void
     */
    public function testSchedulesTheFirstRunForTheNextNightInTheShopTimezone(): void
    {
        $shopTimezone = new DateTimeZone('Europe/Madrid');
        $this->cleanupService->expects($this->once())
            ->method('schedule')
            ->with($this->callback(static function (int $timestamp) use ($shopTimezone): bool {
                $run = (new DateTime('@' . $timestamp))->setTimezone($shopTimezone);

                return $run->format('Y-m-d H') === '2026-11-11 02';
            }));

        $this->scheduler($shopTimezone)->ensureScheduled();
    }

    /**
     * A failure to schedule is logged and never raised, so neither setup nor the task runner tick breaks.
     *
     * @return void
     */
    public function testLogsAFailureToScheduleInsteadOfRaisingIt(): void
    {
        $this->cleanupService->method('schedule')->willThrowException(new \RuntimeException('Lock wait timeout'));
        $shopLogger = $this->createMock(ShopLoggerAdapter::class);
        $shopLogger->expects($this->once())->method('logMessage');
        $this->registerLogger($shopLogger);

        $this->scheduler(new DateTimeZone('UTC'))->ensureScheduled();
    }

    /**
     * Builds the scheduler with a shop clock at 2026-11-10 15:30 in the given timezone.
     *
     * @param DateTimeZone $shopTimezone
     *
     * @return OrderCleanupScheduler
     */
    private function scheduler(DateTimeZone $shopTimezone): OrderCleanupScheduler
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(static function () use ($shopTimezone) {
            return new DateTime('2026-11-10 15:30:00', $shopTimezone);
        });

        return new OrderCleanupScheduler($timezone);
    }

    /**
     * Registers the integration-core logger over the given shop logger.
     *
     * @param ShopLoggerAdapter $shopLogger
     *
     * @return void
     */
    private function registerLogger(ShopLoggerAdapter $shopLogger): void
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getIntegrationName')->willReturn('Magento2');

        ServiceRegister::registerService(ShopLoggerAdapter::CLASS_NAME, static function () use ($shopLogger) {
            return $shopLogger;
        });
        ServiceRegister::registerService(TimeProvider::CLASS_NAME, function () {
            return $this->createStub(TimeProvider::class);
        });
        ServiceRegister::registerService(Configuration::CLASS_NAME, static function () use ($configuration) {
            return $configuration;
        });
    }
}
