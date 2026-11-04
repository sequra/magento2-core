<?php

namespace Sequra\Core\Model\Api\Tasks;

use SeQura\Core\Infrastructure\Serializer\Interfaces\Serializable;
use SeQura\Core\Infrastructure\Serializer\Serializer;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Task;
use Sequra\Core\Model\Api\TrackingSynchronizer;

/**
 * Sends the current tracking list of an order to seQura from the task queue.
 *
 * The list is read when the task runs, and the tasks of one order run one after another, so the list seQura gets
 * last is the one the order has last.
 */
class TrackingSynchronizationTask extends Task
{
    /**
     * @var int
     */
    private $orderId;

    /**
     * @param int $orderId
     */
    public function __construct(int $orderId)
    {
        parent::__construct();
        $this->orderId = $orderId;
    }

    /**
     * Returns the order whose tracking list the task sends.
     *
     * @return int
     */
    public function getOrderId(): int
    {
        return $this->orderId;
    }

    /**
     * @inheritDoc
     */
    public function execute(): void
    {
        /** @var TrackingSynchronizer $synchronizer */
        $synchronizer = ServiceRegister::getService(TrackingSynchronizer::class);
        $synchronizer->synchronize($this->orderId);
        $this->reportProgress(100);
    }

    /**
     * Restores the task from the array toArray() returned.
     *
     * @param mixed[] $array
     *
     * @return self
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction -- integration-core restores tasks statically.
    public static function fromArray(array $array): Serializable
    {
        $orderId = $array['orderId'] ?? 0;

        return new self(is_numeric($orderId) ? (int)$orderId : 0);
    }

    /**
     * @inheritDoc
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return ['orderId' => $this->orderId];
    }

    /**
     * @inheritDoc
     *
     * @return array<string, int>
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $data
     */
    public function __unserialize($data): void
    {
        $this->orderId = $this->toOrderId($data['orderId'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function serialize(): string
    {
        return Serializer::serialize([$this->orderId]);
    }

    /**
     * @inheritDoc
     *
     * @param string $data
     */
    public function unserialize($data): void
    {
        $values = Serializer::unserialize($data);
        $this->orderId = $this->toOrderId(is_array($values) ? ($values[0] ?? 0) : 0);
    }

    /**
     * Reads a stored order ID.
     *
     * @param mixed $value
     *
     * @return int
     */
    private function toOrderId($value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
