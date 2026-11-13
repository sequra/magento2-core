<?php

namespace Sequra\Core\Test\Unit\Repository;

use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use Sequra\Core\ResourceModel\SequraEntity;

/**
 * Resource model whose ID select ignores the filter conditions, as if every row matched when it was read and some
 * stopped matching before the delete ran.
 */
class StaleSelectSequraEntity extends SequraEntity
{
    /**
     * @inheritDoc
     */
    protected function selectIds(?QueryFilter $filter, Entity $entity): array
    {
        return parent::selectIds(null, $entity);
    }
}
