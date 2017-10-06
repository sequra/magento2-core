<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sequra\Core\Model\Adminhtml\Source;
/**
 * Class PaymentAction
 */
class Allowspecificstattypes implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('All Allowed statistics')],
            ['value' => 1, 'label' => __('Specific statistics')]
        ];
    }
}
