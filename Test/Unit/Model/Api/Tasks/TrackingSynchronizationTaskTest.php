<?php

namespace Sequra\Core\Test\Unit\Model\Api\Tasks;

use PHPUnit\Framework\TestCase;
use SeQura\Core\Infrastructure\Serializer\Concrete\JsonSerializer;
use SeQura\Core\Infrastructure\Serializer\Serializer;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Model\Api\Tasks\TrackingSynchronizationTask;
use Sequra\Core\Model\Api\TrackingSynchronizer;

/**
 * Class TrackingSynchronizationTaskTest
 *
 * That the task keeps its order through every way the queue stores it, and synchronizes that order when it runs.
 */
class TrackingSynchronizationTaskTest extends TestCase
{
    /**
     * The order survives every form the queue stores tasks in.
     *
     * @return void
     */
    public function testKeepsItsOrderWhenStored(): void
    {
        $task = new TrackingSynchronizationTask(42);

        $fromArray = TrackingSynchronizationTask::fromArray($task->toArray());
        self::assertInstanceOf(TrackingSynchronizationTask::class, $fromArray);
        self::assertSame(42, $fromArray->getOrderId());

        $restored = new TrackingSynchronizationTask(0);
        $restored->__unserialize($task->__serialize());
        self::assertSame(42, $restored->getOrderId());

        ServiceRegister::registerService(Serializer::CLASS_NAME, static function () {
            return new JsonSerializer();
        });
        $unserialized = new TrackingSynchronizationTask(0);
        $unserialized->unserialize($task->serialize());
        self::assertSame(42, $unserialized->getOrderId());
    }

    /**
     * Running the task synchronizes its order.
     *
     * @return void
     */
    public function testSynchronizesItsOrder(): void
    {
        $synchronizer = $this->createMock(TrackingSynchronizer::class);
        $synchronizer->expects($this->once())->method('synchronize')->with(42);
        ServiceRegister::registerService(TrackingSynchronizer::class, static function () use ($synchronizer) {
            return $synchronizer;
        });

        $task = $this->getMockBuilder(TrackingSynchronizationTask::class)
            ->setConstructorArgs([42])
            ->onlyMethods(['reportProgress'])
            ->getMock();
        $task->expects($this->once())->method('reportProgress')->with(100);

        $task->execute();
    }
}
