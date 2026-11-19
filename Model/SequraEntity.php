<?php

namespace Sequra\Core\Model;

use Magento\Framework\Model\AbstractModel;
use Sequra\Core\ResourceModel\SequraEntity as SequraResourceModel;

class SequraEntity extends AbstractModel
{
    /**
     * Model initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SequraResourceModel::class);
    }
}
