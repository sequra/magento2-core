<?php

namespace Sequra\Core\Repository;

use Magento\Framework\Exception\LocalizedException;
use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\Interfaces\QueueItemRepository as QueueItemRepositoryInterface;
use SeQura\Core\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;
use Sequra\Core\ResourceModel\QueueItemEntity;

class QueueItemRepository extends BaseRepository implements QueueItemRepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
    
    /**
     * Name of the base entity table in database.
     */
    public const TABLE_NAME = 'sequra_queue';

    /**
     * Finds list of earliest queued queue items per queue. Following list of criteria for searching must be satisfied:
     *      - Queue must be without already running queue items
     *      - For one queue only one (oldest queued) item should be returned
     *
     * @param int $priority Queue item priority.
     * @param int $limit Result set limit. By default max 10 earliest queue items will be returned
     *
     * @return QueueItem[] Found queue item list
     *
     * @throws QueryFilterInvalidParamException
     */
    public function findOldestQueuedItems($priority, $limit = 10)
    {
        $entity = new $this->entityClass;

        try {
            // TODO: Call to an undefined method Sequra\Core\ResourceModel\SequraEntity::findOldestQueuedItems()
            // @phpstan-ignore-next-line
            $records = $this->resourceEntity->findOldestQueuedItems($entity, $priority, $limit);
            /** @var QueueItem[] $queuedItems */
            $queuedItems = $this->deserializeEntities($records);
            return $queuedItems;
        } catch (LocalizedException $e) {
            // In case of exception return empty result set.
            return [];
        }
    }

    /**
     * Batch update the status of multiple queue items
     *
     * @param array<int> $ids IDs of queue items to update
     * @param mixed $status Status to set for the items
     *
     * @return void
     */
    public function batchStatusUpdate(array $ids, $status): void
    {
        if (empty($ids)) {
            return;
        }

        // TODO: Call to an undefined method Sequra\Core\ResourceModel\SequraEntity::batchStatusUpdate()
        // @phpstan-ignore-next-line
        $this->resourceEntity->batchStatusUpdate($ids, $status);
    }

    /**
     * Creates or updates given queue item.
     *
     * If queue item id is not set, new queue item will be created otherwise
     * update will be performed.
     *
     * @param QueueItem $queueItem Item to save
     * @param array $additionalWhere List of key/value pairs that must be satisfied upon saving queue item. Key is
     *  queue item property and value is condition value for that property. Example for MySql storage:
     *  $storage->save($queueItem, array('status' => 'queued')) should produce query
     *  UPDATE queue_storage_table SET .... WHERE .... AND status => 'queued'
     * @phpstan-param array<string, mixed> $additionalWhere
     *
     * @return int Id of saved queue item
     *
     * @throws QueueItemSaveException if queue item could not be saved
     */
    public function saveWithCondition(QueueItem $queueItem, array $additionalWhere = []): int
    {
        // TODO: Call to an undefined method Sequra\Core\ResourceModel\SequraEntity::saveWithCondition()
        // @phpstan-ignore-next-line
        return $this->resourceEntity->saveWithCondition($queueItem, $additionalWhere);
    }

    /**
     * Returns resource entity.
     *
     * @return string Resource entity class name.
     */
    protected function getResourceEntity()
    {
        return QueueItemEntity::class;
    }

    /**
     * Translates database records to Sequra entities.
     *
     * @param array $records Array of database records.
     * @phpstan-param array<int, array{data: string, id: int}> $records
     *
     * @return Entity[]
     */
    protected function deserializeEntities($records)
    {
        $entities = [];
        foreach ($records as $record) {

            $requiredKeys = ['data', 'id', 'index_1', 'index_7'];
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $record)) {
                    continue;
                }
            }

            /** @var QueueItem $entity */
            $entity = $this->deserializeEntity($record['data']);
            if ($entity !== null) {
                $entity->setId((int)$record['id']);
                $entity->setStatus($record['index_1']); // @phpstan-ignore-line
                $entity->setLastUpdateTimestamp($record['index_7']); // @phpstan-ignore-line
                $entities[] = $entity;
            }
        }

        return $entities;
    }
}
