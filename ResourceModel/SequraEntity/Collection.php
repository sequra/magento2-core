<?php

namespace Sequra\Core\ResourceModel\SequraEntity;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Sequra\Core\Model\SequraEntity;
use Sequra\Core\ResourceModel\SequraEntity as SequraResourceModel;

/**
 * Class Collection
 *
 * @package Sequra\Core\ResourceModel\SequraEntity
 */
class Collection extends AbstractCollection
{
    /**
     * Collection initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SequraEntity::class, SequraResourceModel::class);
    }
}
