<?php

namespace Sequra\Core\Test\Unit\Services;

use Magento\Framework\App\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\Domain\TaskExecution\Services\RecurringWorkRunner;
use SeQura\Core\Infrastructure\Serializer\Concrete\JsonSerializer;
use SeQura\Core\Infrastructure\Serializer\Serializer;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\TaskEvents\TickEvent;
use SeQura\Core\Infrastructure\Utility\Events\EventBus;
use Sequra\Core\Services\Bootstrap;
use Sequra\Core\Services\BusinessLogic\OrderCleanup\OrderCleanupScheduler;

class BootstrapTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        EventBus::resetInstance();
    }

    /**
     * @return void
     */
    public function testTickRunsTheCoreRecurringWorkAndTheMagentoCleanupScheduling(): void
    {
        EventBus::resetInstance();

        /** @var MockObject&OrderCleanupScheduler $scheduler */
        $scheduler = $this->createMock(OrderCleanupScheduler::class);
        $scheduler->expects($this->once())->method('ensureScheduled');

        /** @var Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(
            Bootstrap::class,
            ['orderCleanupScheduler' => $scheduler]
        );
        $bootstrap->initInstance();

        /** @var MockObject&RecurringWorkRunner $runner */
        $runner = $this->createMock(RecurringWorkRunner::class);
        $runner->expects($this->once())->method('runDue');
        ServiceRegister::registerService(RecurringWorkRunner::class, static function () use ($runner) {
            return $runner;
        });

        /** @var EventBus $eventBus */
        $eventBus = ServiceRegister::getService(EventBus::CLASS_NAME);
        $eventBus->fire(new TickEvent());
    }

    /**
     * @return void
     */
    public function testRepeatedInitRegistersTheTickHandlersOnce(): void
    {
        EventBus::resetInstance();

        /** @var MockObject&OrderCleanupScheduler $scheduler */
        $scheduler = $this->createMock(OrderCleanupScheduler::class);
        $scheduler->expects($this->once())->method('ensureScheduled');

        /** @var Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(
            Bootstrap::class,
            ['orderCleanupScheduler' => $scheduler]
        );
        $bootstrap->initInstance();
        $bootstrap->initInstance();

        /** @var MockObject&RecurringWorkRunner $runner */
        $runner = $this->createMock(RecurringWorkRunner::class);
        $runner->expects($this->once())->method('runDue');
        ServiceRegister::registerService(RecurringWorkRunner::class, static function () use ($runner) {
            return $runner;
        });

        /** @var EventBus $eventBus */
        $eventBus = ServiceRegister::getService(EventBus::CLASS_NAME);
        $eventBus->fire(new TickEvent());
    }

    /**
     * @return void
     */
    public function testRegistersTheJsonSerializer(): void
    {
        /** @var Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();

        $this->assertInstanceOf(JsonSerializer::class, ServiceRegister::getService(Serializer::class));
    }
}
