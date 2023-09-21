<?php

namespace Sequra\Core\ResourceModel\QueueItemEntity;

use Sequra\Core\Model\QueueItemEntity;
use Sequra\Core\ResourceModel\SequraEntity\Collection as SequraEntityCollection;
use Sequra\Core\ResourceModel\QueueItemEntity as QueueItemResourceModel;

/**
 * Class Collection
 *
 * @package Sequra\Core\ResourceModel\QueueItemEntity
 */
class Collection extends SequraEntityCollection
{
    /**
     * Collection initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(QueueItemEntity::class, QueueItemResourceModel::class);
    }
}
