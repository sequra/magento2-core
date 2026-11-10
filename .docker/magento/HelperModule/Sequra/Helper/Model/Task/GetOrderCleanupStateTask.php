<?php

namespace Sequra\Helper\Model\Task;

use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Domain\OrderCleanup\Services\OrderCleanupService;
use SeQura\Core\BusinessLogic\OrderCleanup\Tasks\OrderCleanupTask;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Interfaces\TaskRunnerStatusStorage;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;

class GetOrderCleanupStateTask extends Task
{
    /**
     * Returns the cleanup schedule, the runner liveness, the queued cleanups and the stored references.
     *
     * @param string[] $args
     *
     * @return array<string, mixed>
     */
    public function execute(array $args = [])
    {
        $this->initSeQuraServices();
        /** @var OrderCleanupService $cleanupService */
        $cleanupService = ServiceRegister::getService(OrderCleanupService::class);
        /** @var TaskRunnerStatusStorage $runnerStatusStorage */
        $runnerStatusStorage = ServiceRegister::getService(TaskRunnerStatusStorage::CLASS_NAME);

        return [
            'success' => true,
            'data' => [
                'next_run' => $cleanupService->getNextRun(),
                'runner_alive_since' => (int) $runnerStatusStorage->getStatus()->getAliveSinceTimestamp(),
                'cleanup_task_statuses' => $this->getCleanupTaskStatuses(),
                'existing_references' => $this->getExistingReferences((string) ($args['references'] ?? '')),
            ],
        ];
    }

    /**
     * Returns the status of every queued order cleanup, oldest first.
     *
     * @return string[]
     */
    private function getCleanupTaskStatuses(): array
    {
        $filter = (new QueryFilter())
            ->where('taskType', Operators::EQUALS, OrderCleanupTask::getClassName())
            ->orderBy('id');
        /** @var QueueItem[] $items */
        $items = RepositoryRegistry::getQueueItemRepository()->select($filter);

        return array_map(static function (QueueItem $item): string {
            return $item->getStatus();
        }, $items);
    }

    /**
     * Returns the given seQura order references that are still stored.
     *
     * @param string $references
     *
     * @return string[]
     */
    private function getExistingReferences(string $references): array
    {
        /** @var SeQuraOrderRepositoryInterface $repository */
        $repository = ServiceRegister::getService(SeQuraOrderRepositoryInterface::class);

        return array_values(array_filter(
            array_filter(explode(',', $references)),
            static function (string $reference) use ($repository): bool {
                return $repository->getByOrderReference($reference) !== null;
            }
        ));
    }
}
