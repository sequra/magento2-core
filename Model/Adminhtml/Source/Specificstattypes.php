<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sequra\Core\Model\Adminhtml\Source;

/**
 * Class Specificstattypes
 */
class Specificstattypes implements \Magento\Framework\Option\ArrayInterface
{
    const STAT_AMOUNT = 'amount';
    const STAT_PAYMENT = 'payment_method';
    const STAT_COUNTRY = 'country';
    const STAT_BROWSER = 'browser';
    const STAT_STATUS = 'status';

    public function toOptionArray()
    {
        $stats = array(
            array(
                'value' => self::STAT_AMOUNT,
                'label' => __('Grand total')
            ),
            array(
                'value' => self::STAT_PAYMENT,
                'label' => __('Payment method')
            ),
            array(
                'value' => self::STAT_COUNTRY,
                'label' => __('Country')
            ),
            array(
                'value' => self::STAT_BROWSER,
                'label' => __('Browser')
            ),
            array(
                'value' => self::STAT_STATUS,
                'label' => __('Status')
            )
        );

        return $stats;
    }
}
