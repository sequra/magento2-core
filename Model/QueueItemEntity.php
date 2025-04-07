<?php

namespace Sequra\Core\Model;

use Sequra\Core\ResourceModel\QueueItemEntity as QueueItemResourceModel;

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
