<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

use Sequra\Core\Model\Adminhtml\Source\Endpoint;

class Config extends AbstractConfig
{
    /**
     * SeQura Invoice
     */
    const METHOD_INVOICE = 'sequra_invoice';

    /**
     * SeQura PartPaymeyments
     */
    const METHOD_PARTPAYMENTS = 'sequra_partpayments';

    /**
     * Check whether specified currency code is supported
     *
     * @param  string $code
     * @return bool
     */
    public function isCurrencyCodeSupported($code)
    {
        $isValid = true;
        if (!!$this->getCoreValue('country', $this->storeId)) {
            $availableCountries = explode(
                ',',
                $this->getValue('country', $this->storeId)?:""
            );

            if (!in_array($code, $availableCountries)) {
                $isValid = false;
            }
        }
        return $isValid;
    }

    public function getMaxOrderTotal($storeId = null)
    {
        return $this->getValue('max_order_total', $storeId);
    }

    public function getMinOrderTotal($storeId = null)
    {
        return $this->getValue('min_order_total', $storeId);
    }

    public function getProduct($storeId = null)
    {
        return $this->getValue('product', $storeId);
    }

    public function getScriptUri($storeId = null)
    {
        if ($this->getCoreValue('endpoint', $storeId) == Endpoint::LIVE) {
            return 'https://live.sequracdn.com/assets/sequra-checkout.min.js';
        }
        return 'https://sandbox.sequracdn.com/assets/sequra-checkout.min.js';
    }

    public function getAssetsKey($storeId = null)
    {
        return $this->getCoreValue('assets_key', $storeId);
    }

    public function getMerchantRef($storeId = null)
    {
        return $this->getCoreValue('merchant_ref', $storeId);
    }
}
