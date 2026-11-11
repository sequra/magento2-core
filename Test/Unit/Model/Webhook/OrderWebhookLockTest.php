<?php

namespace Sequra\Core\Test\Unit\Model\Webhook;

use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sequra\Core\Model\Webhook\OrderWebhookLock;

class OrderWebhookLockTest extends TestCase
{
    private const LOCK_NAME = 'sequra_order_webhook_order-1';

    /**
     * @var string[]
     */
    private $events = [];

    /**
     * Runs the process between taking and releasing the lock named after the order.
     *
     * @return void
     */
    public function testRunsTheProcessWhileHoldingTheOrdersLock(): void
    {
        $processed = (new OrderWebhookLock($this->lockManager()))->runExclusively('order-1', function (): void {
            $this->events[] = 'process';
        });

        $this->assertTrue($processed);
        $this->assertSame(['lock', 'process', 'unlock'], $this->events);
    }

    /**
     * Releases the lock even when the process throws, so the next webhook for the order is not stuck.
     *
     * @return void
     */
    public function testReleasesTheLockWhenTheProcessThrows(): void
    {
        try {
            (new OrderWebhookLock($this->lockManager()))->runExclusively('order-1', $this->failingProcess());
            $this->fail('The exception from the process was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Order update failed', $exception->getMessage());
        }

        $this->assertSame(['lock', 'process', 'unlock'], $this->events);
    }

    /**
     * Leaves the order alone when another webhook keeps the lock past the wait.
     *
     * @return void
     */
    public function testSkipsTheProcessWhenTheLockIsNotFreeInTime(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(self::LOCK_NAME, OrderWebhookLock::WAIT_SECONDS)
            ->willReturn(false);
        $lockManager->expects($this->never())->method('unlock');

        $processed = (new OrderWebhookLock($lockManager))->runExclusively('order-1', function (): void {
            $this->events[] = 'process';
        });

        $this->assertFalse($processed);
        $this->assertSame([], $this->events);
    }

    /**
     * Builds a process that records it ran and then fails.
     *
     * @return callable
     */
    private function failingProcess(): callable
    {
        return function (): void {
            $this->events[] = 'process';
            throw new RuntimeException('Order update failed');
        };
    }

    /**
     * Builds a lock manager that grants the order's lock and records when it is taken and released.
     *
     * @return LockManagerInterface&MockObject
     */
    private function lockManager(): LockManagerInterface
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(self::LOCK_NAME, OrderWebhookLock::WAIT_SECONDS)
            ->willReturnCallback(function (): bool {
                $this->events[] = 'lock';

                return true;
            });
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(self::LOCK_NAME)
            ->willReturnCallback(function (): bool {
                $this->events[] = 'unlock';

                return true;
            });

        return $lockManager;
    }
}
