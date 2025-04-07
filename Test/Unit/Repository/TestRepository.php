<?php

namespace Sequra\Core\Test\Unit\Repository;

use Sequra\Core\Repository\BaseRepository;

class TestRepository extends BaseRepository
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'sequra_test';
}
