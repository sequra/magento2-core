<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sequra\Core\Model\Adminhtml\Source;

/**
 * Class Endpoint
 */
class Endpoint implements \Magento\Framework\Option\ArrayInterface
{
    const LIVE = 'https://live.sequrapi.com/orders';

    const SANDBOX = 'https://sandbox.sequrapi.com/orders';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Endpoint::SANDBOX,
                'label' => __('Sandbox')
            ],
            [
                'value' => Endpoint::LIVE,
                'label' => __('Live')
            ]
        ];
    }
}
