<?php

/**
 * Copyright © 2020 ZhenIT Software. All rights reserved.
 */

namespace Sequra\Core\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\HTTP\Client\Curl;
use Sequra\Core\Model\Adminhtml\Source\Endpoint;

/**
 * Class TransactionSale
 */
class OrderUpdateTransaction implements ClientInterface
{
    /**
     * @var \Magento\Payment\Gateway\ConfigInterface
     */
    private $config;

    /**
     * @param Logger $logger
     * @param \Magento\Payment\Gateway\Config\Config $config
     */
    public function __construct(
        \Magento\Payment\Gateway\Config\Config $config
    ) {
        $this->config = $config;
        $this->config->setPathPattern('sequra/%s/%s');
        $this->config->setMethodCode('core');
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return null
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $client = new \Sequra\PhpClient\Client(
            $this->config->getValue('user_name'),
            $this->config->getValue('user_secret'),
            $this->config->getValue('endpoint'),
            $this->config->getValue('endpoint')!=Endpoint::LIVE
        );
        $client->orderUpdate($transferObject->getBody());
        return [
            "success" => $client->succeeded(),
            "data" => $transferObject->getBody()
        ];
    }
}
