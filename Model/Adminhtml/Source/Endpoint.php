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
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => Endpoint::SANDBOX,
                'label' => __('Sandbox')
            ],
            [
                'value' => Endpoint::LIVE,
                'label' => __('Live')
            ]
        ];

        if (!in_array(
            $endpoint = $this->scopeConfig->getValue('sequra/core/endpoint'),
            [Endpoint::LIVE,Endpoint::SANDBOX]
        )) {
            $options[] = [
                'value' => $endpoint,
                'label' => "Custom (" . $endpoint . ")"
            ];
        }
        return $options;
    }
}
