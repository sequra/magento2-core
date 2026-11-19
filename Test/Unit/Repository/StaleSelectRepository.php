<?php

namespace Sequra\Core\Test\Unit\Repository;

class StaleSelectRepository extends TestRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;

    /**
     * @inheritDoc
     */
    protected function getResourceEntity()
    {
        return StaleSelectSequraEntity::class;
    }
}
