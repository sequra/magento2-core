<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sequra\Core\Model\Adminhtml\Source;

/**
 * Class Hour
 */
class StatsPeriod implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('None (no stats at all)')
            ],
            [
                'value' => 1,
                'label' => __('day')
            ],
            [
                'value' => 2,
                'label' => __('2 days')
            ],
            [
                'value' => 3,
                'label' => __('3 days')
            ],
            [
                'value' => 4,
                'label' => __('4 days')
            ],
            [
                'value' => 5,
                'label' => __('5 days')
            ],
            [
                'value' => 6,
                'label' => __('6 days')
            ],
            [
                'value' => 7,
                'label' => __('7 days')
            ],
        ];
    }
}
