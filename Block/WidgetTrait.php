<?php

namespace Sequra\Core\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\Request;

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
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var ScopeResolverInterface
     */
    protected $scopeResolver;
    /**
     * @var Request
     */
    protected $request;

    /**
     * Returns current country code
     *
     * @return string
     */
    private function getCurrentCountry(): string
    {
        // The store view's locale always carries a region (Magento only offers full `xx_YY`
        // locales, and falls back to en_US), so this names a real country for every store.
        return (string)\Locale::getRegion((string)$this->localeResolver->getLocale());
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

    /**
     * Returns current currency code
     *
     * @return string
     * @throws LocalizedException
     */
    private function getCurrentCurrency(): string
    {
        /**
         * @var Store $store
         */
        $store = $this->scopeResolver->getScope();

        return $store->getCurrentCurrency()->getCode();
    }

    /**
     * Returns customer ip address
     *
     * @return string
     */
    private function getCustomerIpAddress(): string
    {
        return $this->request->getClientIp();
    }
}
