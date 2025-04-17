<?php

namespace Sequra\Core\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class VoidTransaction implements ClientInterface
{
    /**
     * Place request
     *
     * @param TransferInterface $transferObject
     *
     * @return array<string, bool>
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        return [
            "success" => true,
        ];
    }
}
