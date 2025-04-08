<?php

namespace Sequra\Core\Test\Unit\Repository;

use Sequra\Core\Repository\QueueItemRepository;

class TestQueueItemRepository extends QueueItemRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    public const TABLE_NAME = 'sequra_test';
}
