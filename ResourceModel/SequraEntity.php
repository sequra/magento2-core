<?php

namespace Sequra\Core\ResourceModel;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryCondition;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\Utility\IndexHelper;

class SequraEntity extends AbstractDb
{

    // phpcs:disable Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    /**
     * Resource model initialization.
     *
     * @return void
     */
    protected function _construct()
    {
    }
    // phpcs:enable
    
    /**
     * Set resource model table name.
     *
     * @param string $tableName Name of the database table.
     *
     * @return void
     */
    public function setTableName($tableName)
    {
        $this->_init($tableName, 'id');
    }

    /**
     * Selects all records from Sequra entity table.
     *
     * @return Entity[] Sequra entity records.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function selectAll()
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return [];
        }
        $select = $connection->select()
            ->from($this->getMainTable());

        $result = $connection->fetchAll($select);

        return !empty($result) ? $result : [];
    }

    /**
     * Performs a select query over a specific type of entity with given Sequra query filter.
     *
     * @param QueryFilter|null $filter Sequra query filter.
     * @param Entity $entity Sequra entity.
     *
     * @return array Array of selected records.
     * @phpstan-return array<int, array{data: string, id: int}>
     *
     * @throws QueryFilterInvalidParamException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function selectEntities($filter, $entity)
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return [];
        }

        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('type = ?', $entity->getConfig()->getType());

        if ($filter !== null) {
            $fieldIndexMap = IndexHelper::mapFieldsToIndexes($entity);

            if (!empty($filter->getConditions())) {
                $select->where($this->buildWhereCondition($filter, $fieldIndexMap));
            }

            if ($filter->getLimit()) {
                $select->limit($filter->getLimit(), $filter->getOffset());
            }

            $select = $this->buildOrderBy($select, $filter, $fieldIndexMap);
        }

        $result = $connection->fetchAll($select);

        return !empty($result) ? $result : [];
    }

    /**
     * Inserts a new record in Sequra entity table.
     *
     * @param Entity $entity Sequra entity.
     *
     * @return int ID of the inserted record.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function saveEntity($entity)
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return 0;
        }

        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $data = $this->prepareDataForInsertOrUpdate($entity, $indexes);
        $data['type'] = $entity->getConfig()->getType();

        $connection->insert($this->getMainTable(), $data);

        return (int)$connection->fetchOne('SELECT last_insert_id()');
    }

    /**
     * Updates an existing record in Sequra entity table identified by ID.
     *
     * @param Entity $entity Sequra entity.
     *
     * @return bool Returns TRUE if updateEntity has been successful, otherwise returns FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateEntity($entity)
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return false;
        }

        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $data = $this->prepareDataForInsertOrUpdate($entity, $indexes);
        $whereCondition = [$this->getIdFieldName() . '=?' => (int)$entity->getId()];

        $rows = $connection->update($this->getMainTable(), $data, $whereCondition);

        return $rows === 1;
    }

    /**
     * Deletes a record from Sequra entity table.
     *
     * @param int $id ID of the record.
     *
     * @return bool Returns TRUE if updateEntity has been successful, otherwise returns FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteEntity($id)
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return false;
        }

        $rows = $connection->delete(
            $this->getMainTable(),
            [
                $connection->quoteInto('id = ?', $id),
            ]
        );

        return $rows === 1;
    }

    /**
     * Prepares data for inserting a new record or updating an existing one.
     *
     * @param Entity $entity Sequra entity object.
     * @param array $indexes Array of index values.
     * @phpstan-param array<string, mixed> $indexes
     *
     * @return array Prepared record for inserting or updating.
     * @phpstan-return array<string, mixed>
     */
    protected function prepareDataForInsertOrUpdate(Entity $entity, array $indexes)
    {
        $record = ['data' => $this->serializeEntity($entity)];

        foreach ($indexes as $index => $value) {
            $record['index_' . $index] = $value;
        }

        return $record;
    }

    /**
     * Returns index mapped to given property.
     *
     * @param string $property Property name.
     * @param string $entityType Entity type.
     *
     * @return string|null Index column in Sequra entity table.
     */
    protected function getIndexMapping($property, $entityType)
    {
        $entity = new $entityType;
        if (!$entity instanceof Entity) {
            return null;
        }
        $indexMapping = IndexHelper::mapFieldsToIndexes($entity);

        if (array_key_exists($property, $indexMapping)) {
            return 'index_' . $indexMapping[$property];
        }

        return null;
    }

    /**
     * Builds WHERE condition part of SELECT query.
     *
     * @param QueryFilter $filter Sequra query filter.
     * @param array $fieldIndexMap Array of index mappings.
     * @phpstan-param array<string, int> $fieldIndexMap
     *
     * @return string WHERE part of SELECT query.
     *
     * @throws \SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function buildWhereCondition(QueryFilter $filter, array $fieldIndexMap)
    {
        $whereCondition = '';
        if ($filter->getConditions()) {
            foreach ($filter->getConditions() as $index => $condition) {
                if ($index !== 0) {
                    $whereCondition .= ' ' . $condition->getChainOperator() . ' ';
                }

                if ($condition->getColumn() === 'id' && $this->getConnection()) {
                    $whereCondition .= 'id = ' . $this->getConnection()->quote($condition->getValue());
                    continue;
                }

                if (!array_key_exists($condition->getColumn(), $fieldIndexMap)) {
                    throw new QueryFilterInvalidParamException(
                        sprintf('Field %s is not indexed!', $condition->getColumn())
                    );
                }

                $whereCondition .= $this->addCondition($condition, $fieldIndexMap);
            }
        }

        return $whereCondition;
    }

    /**
     * Filters records by given condition.
     *
     * @param QueryCondition $condition Query condition object.
     * @param array $indexMap Map of property indexes.
     * @phpstan-param array<string, int> $indexMap
     *
     * @return string A single WHERE condition.
     */
    private function addCondition(QueryCondition $condition, array $indexMap)
    {
        $column = $condition->getColumn();
        $columnName = $column === 'id' ? 'id' : 'index_' . $indexMap[$column];
        if ($column === 'id') {
            $conditionValue = (int)$condition->getValue(); // @phpstan-ignore-line
        } else {
            $conditionValue = IndexHelper::castFieldValue($condition->getValue(), $condition->getValueType());
        }

        if (in_array($condition->getOperator(), [Operators::NOT_IN, Operators::IN], true)) {
            /**
             * @var array<int, string|int|float> $arr
             */
            $arr = $condition->getValue();
            $values = array_map(static function ($item) {
                if (is_string($item)) {
                    return "'$item'";
                }

                if (is_int($item)) {
                    /**
                     * @var int $val
                     */
                    $val = IndexHelper::castFieldValue($item, 'integer');
                    return "'{$val}'";
                }

                /**
                 * @var float $val
                 */
                $val = IndexHelper::castFieldValue($item, 'double');

                return "'{$val}'";
            }, $arr);
            $conditionValue = '(' . implode(',', $values) . ')';
        } else {
            // TODO: Part $conditionValue (array|int|string|null) of encapsed string cannot be cast to string.
            // @phpstan-ignore-next-line
            $conditionValue = "'$conditionValue'";
        }

        return $columnName . ' ' . $condition->getOperator()
            . (!in_array($condition->getOperator(), [Operators::NULL, Operators::NOT_NULL], true)
                ? $conditionValue : ''
            );
    }

    /**
     * Builds ORDER BY part of SELECT query.
     *
     * @param Select $select Magento SELECT query object.
     * @param QueryFilter $filter Sequra query filter.
     * @param array $fieldIndexMap Array of index mappings.
     * @phpstan-param array<string, int> $fieldIndexMap
     *
     * @return Select Updated Magento SELECT query object.
     *
     * @throws QueryFilterInvalidParamException
     */
    private function buildOrderBy(Select $select, $filter, array $fieldIndexMap)
    {
        $orderByColumn = $filter->getOrderByColumn();
        if ($orderByColumn) {
            $indexedColumn = null;
            if ($orderByColumn === 'id') {
                $indexedColumn = 'id';
            } elseif (array_key_exists($orderByColumn, $fieldIndexMap)) {
                $indexedColumn = 'index_' . $fieldIndexMap[$orderByColumn];
            }

            if ($indexedColumn === null) {
                throw new QueryFilterInvalidParamException(
                    sprintf('Unknown or not indexed OrderBy column %s', $orderByColumn)
                );
            }

            $select->order($indexedColumn . ' ' . $filter->getOrderDirection());
        }

        return $select;
    }

    /**
     * Serializes SequraEntity to string.
     *
     * @param Entity $entity SequraEntity object to be serialized
     *
     * @return string Serialized entity
     */
    private function serializeEntity(Entity $entity)
    {
        return (string) json_encode($entity->toArray());
    }
}
