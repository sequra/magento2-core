<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

//@todo: Implement toknization as option

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'core';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'sequra' => [
                self::CODE => []
            ]
        ];
    }
}
