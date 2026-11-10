<?php

namespace Sequra\Core\Test\Unit\Repository;

use Sequra\Core\ResourceModel\SequraEntity;

/**
 * Resource model whose select ignores the filter conditions, as if every row matched when it was read and some
 * stopped matching before the delete ran.
 */
class StaleSelectSequraEntity extends SequraEntity
{
    /**
     * @inheritDoc
     */
    public function selectEntities($filter, $entity)
    {
        return parent::selectEntities(null, $entity);
    }
}
