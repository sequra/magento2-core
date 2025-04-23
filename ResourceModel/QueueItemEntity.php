<?php

namespace Sequra\Core\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\Utility\IndexHelper;
use SeQura\Core\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;

class QueueItemEntity extends SequraEntity
{
    /**
     * Finds list of earliest queued queue items per queue. Following list of criteria for searching must be satisfied:
     *      - Queue must be without already running queue items
     *      - For one queue only one (oldest queued) item should be returned
     *
     * @param Entity $entity Sequra entity.
     * @param int $priority Queue item priority.
     * @param int $limit Result set limit. By default max 10 earliest queue items will be returned
     *
     * @return QueueItem[] Found queue item list
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function findOldestQueuedItems($entity, $priority, $limit = 10)
    {
        $runningQueueNames = $this->getRunningQueueNames($entity);

        return $this->getQueuedItems($runningQueueNames, $priority, $limit);
    }

    /**
     * Returns names of queues containing items that are currently in progress.
     *
     * @param Entity $entity Sequra entity.
     *
     * @return array<string> Names of queues containing items that are currently in progress.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function getRunningQueueNames($entity)
    {
        $filter = new QueryFilter();
        $filter->where('status', Operators::EQUALS, QueueItem::IN_PROGRESS);

        /** @var QueueItem[] $runningQueueItems */
        $runningQueueItems = $this->selectEntities($filter, $entity);
        $fieldIndexMap = IndexHelper::mapFieldsToIndexes($entity);
        $queueNameIndex = 'index_' . $fieldIndexMap['queueName'];

        return array_map(
            static function ($runningQueueItem) use ($queueNameIndex) {
                // TODO: Cannot access offset non-falsy-string on SeQura\Core\Infrastructure\TaskExecution\QueueItem
                // @phpstan-ignore-next-line
                return $runningQueueItem[$queueNameIndex];
            },
            $runningQueueItems
        );
    }

    /**
     * Returns all queued items.
     *
     * @param array<string> $runningQueueNames Array of queues containing items that are currently in progress.
     * @param int $priority Queue item priority.
     * @param int $limit Maximum number of records that can be retrieved.
     *
     * @return QueueItem[] Array of queued items.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getQueuedItems(array $runningQueueNames, $priority, $limit)
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return [];
        }

        $queueNameIndex = $this->getIndexMapping('queueName', QueueItem::getClassName());
        /**
         * @var int $castedPriorityValue
         */
        $castedPriorityValue = IndexHelper::castFieldValue($priority, 'integer');
        $condition = $this->buildWhereString(
            [
                'type' => 'QueueItem',
                $this->getIndexMapping('status', QueueItem::getClassName()) => QueueItem::QUEUED,
                $this->getIndexMapping('priority', QueueItem::getClassName()) => $castedPriorityValue,
            ]
        );

        if (!empty($runningQueueNames)) {
            $quotedNames = array_map(
                function ($name) use ($connection) {
                    return $connection->quote($name);
                },
                $runningQueueNames
            );
            $condition .= sprintf(' AND ' . $queueNameIndex . ' NOT IN (%s)', implode(', ', $quotedNames));
        }

        // TODO: Possible raw SQL statement detected
        // phpcs:disable Magento2.SQL.RawQuery.FoundRawSql
        $query = 'SELECT queueTable.id, queueTable.index_1, queueTable.index_7, queueTable.data '
            . 'FROM ( '
            . 'SELECT ' . $queueNameIndex . ', MIN(id) AS id '
            . 'FROM ' . $this->getMainTable() . ' '
            . 'WHERE ' . $condition . ' '
            . 'GROUP BY ' . $queueNameIndex . ' '
            . 'LIMIT ' . $limit
            . ' ) AS queueView '
            . 'INNER JOIN ' . $this->getMainTable() . ' AS queueTable '
            . 'ON queueView.id = queueTable.id';
        // phpcs:enable

        $records = $connection->fetchAll($query);

        return \is_array($records) ? $records : [];
    }

    /**
     * Builds where condition string based on given key/value parameters.
     *
     * @param array $whereFields Key value pairs of where condition
     * @phpstan-param array<string, int|string|null> $whereFields
     *
     * @return string Properly sanitized where condition string
     */
    private function buildWhereString(array $whereFields = [])
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return '';
        }
        $where = [];
        foreach ($whereFields as $field => $value) {
            $where[] = $field . Operators::EQUALS . $connection->quote($value);
        }

        return implode(' AND ', $where);
    }

    /**
     * Creates or updates given queue item.
     *
     * If queue item id is not set, new queue item will be created otherwise update will be performed.
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
    public function saveWithCondition(QueueItem $queueItem, array $additionalWhere = [])
    {
        $savedItemId = null;

        try {
            $itemId = $queueItem->getId();
            if ($itemId === null || $itemId <= 0) {
                $savedItemId = $this->saveEntity($queueItem);
            } else {
                $this->updateQueueItem($queueItem, $additionalWhere);
            }
        } catch (\Exception $e) {
            throw new QueueItemSaveException('Failed to save queue item.', 0, $e);
        }

        return $savedItemId ?: $itemId;
    }

    /**
     * Updates status of a batch of queue items.
     *
     * @param array<int> $ids
     * @param string $status
     *
     * @return void
     * @throws LocalizedException
     */
    public function batchStatusUpdate(array $ids, string $status)
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return;
        }

        $lastUpdateTime = IndexHelper::castFieldValue(time(), 'integer');
        $data = [
            $this->getIndexMapping('status', QueueItem::getClassName()) => $status,
            $this->getIndexMapping('lastUpdateTimestamp', QueueItem::getClassName()) => $lastUpdateTime,
        ];
        $whereCondition = [$this->getIdFieldName() . ' IN (?)' => $ids];

        $connection->update($this->getMainTable(), $data, $whereCondition);
    }

    /**
     * Updates queue item.
     *
     * @param QueueItem $queueItem Queue item entity.
     * @param array $additionalWhere Array of additional where conditions.
     * @phpstan-param array<string, mixed> $additionalWhere
     *
     * @return void
     *
     * @throws QueueItemSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function updateQueueItem($queueItem, array $additionalWhere)
    {
        $filter = new QueryFilter();
        $filter->where('id', Operators::EQUALS, $queueItem->getId());

        foreach ($additionalWhere as $name => $value) {
            if ($value === null) {
                $filter->where($name, Operators::NULL);
            } else {
                $filter->where($name, Operators::EQUALS, $value);
            }
        }

        $filter->setLimit(1);
        $results = $this->selectEntities($filter, new QueueItem());
        if (empty($results)) {
            throw new QueueItemSaveException("Can not update queue item with id {$queueItem->getId()}.");
        }

        $this->updateEntity($queueItem);
    }
}
