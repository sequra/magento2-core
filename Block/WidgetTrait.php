<?php

namespace Sequra\Core\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Magento\Tests\NamingConvention\true\string;
use Magento\Framework\Exception\LocalizedException;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;

/**
 * Implement common behavior for all widgets
 */
trait WidgetTrait
{
    /**
     * @var ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var CurrencyValidator
     */
    protected $currencyValidator;
    /**
     * @var IpAddressValidator
     */
    protected $ipAddressValidator;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * Validates current currency and ip address
     * 
     * @return bool
     * @throws LocalizedException
     */
    private function validate(): bool
    {
        /**
         * @var Store $store
         */
        $store = $this->scopeResolver->getScope();
        $subject = [
            'currency' => $store->getCurrentCurrency()->getCode(),
            'storeId' => $store->getId()
        ];

        if (!$this->currencyValidator->validate($subject)->isValid()) {
            return false;
        }

        if (!$this->ipAddressValidator->validate($subject)->isValid()) {
            return false;
        }

        return true;
    }

    /**
     * Returns current country code
     * 
     * @return string
     */
    private function getCurrentCountry(): string
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Returns shipping address country code
     * 
     * @return string
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getShippingAddressCountry(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $shippingAddress = $quote ? $quote->getShippingAddress() : null;

        return ($shippingAddress && $shippingAddress->getCountryId()) ?
            $shippingAddress->getCountryId() : '';
    }
}
