<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sequra\Core\Model\Adminhtml\Source;

/**
 * Class Hour
 */
class Hour implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('00:00 AM')
            ],
            [
                'value' => 1,
                'label' => __('01:00 AM')
            ],
            [
                'value' => 2,
                'label' => __('02:00 AM')
            ],
            [
                'value' => 3,
                'label' => __('03:00 AM')
            ],
            [
                'value' => 4,
                'label' => __('04:00 AM')
            ],
            [
                'value' => 5,
                'label' => __('05:00 AM')
            ],
            [
                'value' => 6,
                'label' => __('06:00 AM')
            ],
        ];
    }
}
