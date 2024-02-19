<?php

namespace Sequra\Core\Repository;

use Magento\Framework\Exception\LocalizedException;
use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\Interfaces\QueueItemRepository as QueueItemRepositoryInterface;
use SeQura\Core\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;
use Sequra\Core\ResourceModel\QueueItemEntity;

/**
 * Class QueueItemRepository
 *
 * @package Sequra\Core\Repository
 * @property QueueItemEntity $resourceEntity
 */
class QueueItemRepository extends BaseRepository implements QueueItemRepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'sequra_queue';

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
        $queuedItems = [];
        $entity = new $this->entityClass;

        try {
            $records = $this->resourceEntity->findOldestQueuedItems($entity, $priority, $limit);
            /** @var QueueItem[] $queuedItems */
            $queuedItems = $this->deserializeEntities($records);
        } catch (LocalizedException $e) {
            // In case of exception return empty result set.
        }

        return $queuedItems;
    }

    /**
     * @inheridoc
     */
    public function batchStatusUpdate(array $ids, $status)
    {
        if (empty($ids)) {
            return;
        }

        $this->resourceEntity->batchStatusUpdate($ids, $status);
    }

    /**
     * Creates or updates given queue item. If queue item id is not set, new queue item will be created otherwise
     * update will be performed.
     *
     * @param QueueItem $queueItem Item to save
     * @param array $additionalWhere List of key/value pairs that must be satisfied upon saving queue item. Key is
     *  queue item property and value is condition value for that property. Example for MySql storage:
     *  $storage->save($queueItem, array('status' => 'queued')) should produce query
     *  UPDATE queue_storage_table SET .... WHERE .... AND status => 'queued'
     *
     * @return int Id of saved queue item
     *
     * @throws QueueItemSaveException if queue item could not be saved
     */
    public function saveWithCondition(QueueItem $queueItem, array $additionalWhere = [])
    {
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
     *
     * @return Entity[]
     */
    protected function deserializeEntities($records)
    {
        $entities = [];
        foreach ($records as $record) {
            /** @var QueueItem $entity */
            $entity = $this->deserializeEntity($record['data']);
            if ($entity !== null) {
                $entity->setId((int)$record['id']);
                $entity->setStatus($record['index_1']);
                $entity->setLastUpdateTimestamp($record['index_7']);
                $entities[] = $entity;
            }
        }

        return $entities;
    }
}
