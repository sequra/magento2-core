<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

use \Sequra\Core\Model\Adminhtml\Source\Endpoint;

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
     * Currency codes supported by SeQura methods
     *
     * @var string[]
     */
    protected $_supportedCurrencyCodes = [
        'EUR',
    ];

    /**
     * Merchant country supported by SeQura methods
     *
     * @var string[]
     */
    protected $_supportedCountryCodes = [
        'ES'
    ];

    /**
     * Buyer country supported by SeQura methods
     *
     * @var string[]
     */
    protected $_supportedBuyerCountryCodes = [
        'ES'
    ];

    /**
     * Locale codes supported by misc images (marks, shortcuts etc)
     *
     * @var string[]
     * @link https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECButtonIntegration#id089QD0O0TX4__id08AH904I0YK
     */
    protected $_supportedImageLocales = [
        'es_ES',
    ];

    /**
     * Check whether method available for checkout or not
     * Logic based on merchant country, methods dependence
     *
     * @param string|null $methodCode
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodAvailable($methodCode = null)
    {
        $result = parent::isMethodAvailable($methodCode);

        switch ($methodCode) {
            case self::METHOD_WPP_EXPRESS:
            case self::METHOD_WPS_EXPRESS:
                if ($this->isMethodActive(self::METHOD_PAYFLOWPRO)
                    || $this->isMethodActive(self::METHOD_PAYMENT_PRO)
                ) {
                    $result = true;
                }
                break;
            case self::METHOD_WPP_BML:
            case self::METHOD_WPS_BML:
                // check for express payments dependence
                if (!$this->isMethodActive(self::METHOD_WPP_EXPRESS)
                    && !$this->isMethodActive(self::METHOD_WPS_EXPRESS)
                ) {
                    $result = false;
                }
                break;
            case self::METHOD_WPP_PE_EXPRESS:
                // check for direct payments dependence
                if ($this->isMethodActive(self::METHOD_PAYFLOWLINK)
                    || $this->isMethodActive(self::METHOD_PAYFLOWADVANCED)
                ) {
                    $result = true;
                } elseif (!$this->isMethodActive(self::METHOD_PAYFLOWPRO)) {
                    $result = false;
                }
                break;
            case self::METHOD_WPP_PE_BML:
                // check for express payments dependence
                if (!$this->isMethodActive(self::METHOD_WPP_PE_EXPRESS)) {
                    $result = false;
                }
                break;
            case self::METHOD_BILLING_AGREEMENT:
                $result = $this->isWppApiAvailabe();
                break;
        }
        return $result;
    }

    /**
     * Return merchant country codes supported by PayPal
     *
     * @return string[]
     */
    public function getSupportedMerchantCountryCodes()
    {
        return $this->_supportedCountryCodes;
    }

    /**
     * Return buyer country codes supported by PayPal
     *
     * @return string[]
     */
    public function getSupportedBuyerCountryCodes()
    {
        return $this->_supportedBuyerCountryCodes;
    }

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
     * Check whether method supported for specified country or not
     * Use $_methodCode and merchant country by default
     *
     * @param string|null $method
     * @param string|null $countryCode
     * @return bool
     */
    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        if ($method === null) {
            $method = $this->getMethodCode();
        }
        if ($countryCode === null) {
            $countryCode = $this->getMerchantCountry();
        }
        return in_array($method, $this->getCountryMethods($countryCode));
    }

    /**
     * Return list of allowed methods for specified country iso code
     *
     * @param string|null $countryCode 2-letters iso code
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCountryMethods($countryCode = null)
    {
        $countryMethods = [
            'other' => [
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'US' => [
                self::METHOD_PAYFLOWADVANCED,
                self::METHOD_PAYFLOWPRO,
                self::METHOD_PAYFLOWLINK,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_WPP_BML,
                self::METHOD_BILLING_AGREEMENT,
                self::METHOD_WPP_PE_EXPRESS,
                self::METHOD_WPP_PE_BML,
            ],
            'CA' => [
                self::METHOD_PAYFLOWPRO,
                self::METHOD_PAYFLOWLINK,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
                self::METHOD_WPP_PE_EXPRESS,
            ],
            'GB' => [
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'AU' => [
                self::METHOD_PAYFLOWPRO,
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'NZ' => [
                self::METHOD_PAYFLOWPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'JP' => [
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'FR' => [
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'IT' => [
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'ES' => [
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'HK' => [
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
            'DE' => [
                self::METHOD_WPP_EXPRESS,
                self::METHOD_BILLING_AGREEMENT,
            ],
        ];
        if ($countryCode === null) {
            return $countryMethods;
        }
        return isset($countryMethods[$countryCode]) ? $countryMethods[$countryCode] : $countryMethods['other'];
    }

    /**
     * Check whether specified currency code is supported
     *
     * @param string $code
     * @return bool
     */
    public function isCurrencyCodeSupported($code)
    {
        if (in_array($code, $this->_supportedCurrencyCodes)) {
            return true;
        }
        if ($this->getMerchantCountry() == 'BR' && $code == 'BRL') {
            return true;
        }
        if ($this->getMerchantCountry() == 'MY' && $code == 'MYR') {
            return true;
        }
        if ($this->getMerchantCountry() == 'TR' && $code == 'TRY') {
            return true;
        }
        return false;
    }

    public function getMaxOrderTotal($storeId = null){
        return $this->getValue('max_order_total', $storeId);
    }

    public function getMinOrderTotal($storeId = null){
        return $this->getValue('min_order_total', $storeId);
    }

    public function getProduct($storeId = null){
        return $this->getValue('product', $storeId);
    }

    public function getAssetsKey($storeId = null){
        return $this->getCoreValue('assets_key', $storeId);
    }

    public function getCostUrl($product,$storeId = null){
        $url = 'https://';
        if($this->getCoreValue('endpoint', $storeId)==Endpoint::LIVE){
            $url .='live';
        }else{
            $url .='sandbox';
        }
        $url .= '.sequracdn.com/scripts/'.
            $this->getCoreValue('merchant_ref', $storeId).'/'.
            $this->getAssetsKey($storeId).'/'.
            $product.'_cost.js';
        return $url;
    }
}
