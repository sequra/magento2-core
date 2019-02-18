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
     * Locale codes supported by misc images (marks, shortcuts etc)
     *
     * @var  string[]
     * @link https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECButtonIntegration#id089QD0O0TX4__id08AH904I0YK
     */
    protected $_supportedImageLocales = [
        'es_ES',
    ];

    /**
     * Check whether method available for checkout or not
     * Logic based on merchant country, methods dependence
     *
     * @param                                        string|null $methodCode
     * @return                                       bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    /*
    public function isMethodAvailable($methodCode = null)
    {
        $result = parent::isMethodAvailable($methodCode);

        switch ($methodCode) {
            case self::METHOD_INVOICE:
                if ($this->isMethodActive(self::METHOD_INVOICE)) {
                    $result = true;
                }
                break;
            case self::METHOD_PARTPAYMENTS:
                if (!$this->isMethodActive(self::METHOD_PARTPAYMENTS)) {
                    $result = false;
                }
                break;
        }
        return $result;
    }
    */
    /**
     * Return merchant country code, use default country if it not specified in General settings
     *
     * @return string
     */
    public function getMerchantCountry()
    {
        $countryCode = $this->_scopeConfig->getValue(
            $this->_mapGeneralFieldset('merchant_country'),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->_storeId
        );
        if (!$countryCode) {
            $countryCode = $this->directoryHelper->getDefaultCountry($this->_storeId);
        }
        return $countryCode;
    }

    /**
     * Check whether specified currency code is supported
     *
     * @param  string $code
     * @return bool
     */
    public function isCurrencyCodeSupported($code)
    {
        $isValid = true;
        if (!!$this->config->getCoreValue('country', $storeId)) {
            $availableCountries = explode(
                ',',
                $this->config->getValue('country', $storeId)
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
