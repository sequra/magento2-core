<?php

namespace Sequra\Core\Model;

use Sequra\Core\ResourceModel\QueueItemEntity as QueueItemResourceModel;

/**
 * Class QueueItemEntity
 *
 * @package Sequra\Core\Model
 */
class QueueItemEntity extends SequraEntity
{
    /**
     * Model initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(QueueItemResourceModel::class);
    }
}
