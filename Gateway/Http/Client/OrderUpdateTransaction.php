<?php
/**
 * Copyright © 2020 ZhenIT Software. All rights reserved.
 */

namespace Sequra\Core\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\HTTP\Client\Curl;

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
        $this->config->setPathPattern('zhenit_redsys/%s/%s');
        $this->config->setMethodCode('api');
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return null
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $client = new Curl();
        $client->post(
            $this->config->getValue('test')?Constants::SANDBOX_ENDPOINT:Constants::PRODUCTION_ENDPOINT,
            $transferObject->getBody()
        );
        $data = $client->getBody();
        return json_decode($data, true);
    }
}
