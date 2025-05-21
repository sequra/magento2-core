<?php

namespace Sequra\Core\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Magento\Framework\View\Element\Template;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;

/**
 * *
 * Implements required logic to show mini-widget according to configuration on product listing page
 */
class MiniWidget extends Template
{
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param CurrencyValidator $currencyValidator
     * @param IpAddressValidator $ipAddressValidator
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface      $localeResolver,
        CurrencyValidator      $currencyValidator,
        IpAddressValidator     $ipAddressValidator,
        Context                $context,
        CheckoutSession        $checkoutSession
    )
    {
        parent::__construct($context);
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Validate before producing html
     *
     * @return string
     */
    protected function _toHtml()
    {
        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();
        $subject = ['currency' => $store->getCurrentCurrency()->getCode(), 'storeId' => $store->getId()];

        if (!$this->currencyValidator->validate($subject)->isValid()) {
            return '';
        }

        if (!$this->ipAddressValidator->validate($subject)->isValid()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Prepare the available mini-widget to show in the product listing frontend based on the configuration and the current context
     *
     * @return array
     * @phpstan-return array<int,
     *  array{
     *      product: string,
     *      campaign: string,
     *      priceSel: string,
     *      dest: string,
     *      theme: string|null,
     *      reverse: '0',
     *      minAmount: int|null,
     *      maxAmount: int|null,
     *      altPriceSel: string,
     *      altTriggerSelector: string,
     *      miniWidgetMessage: string,
     *      miniWidgetBelowLimitMessage: string
     *  }>
     */
    public function getAvailableMiniWidgets()
    {
        $quote = $this->checkoutSession->getQuote();
        $shippingAddress = $quote ? $quote->getShippingAddress() : null;
        $shippingCountry = ($shippingAddress && $shippingAddress->getCountryId()) ?
            $shippingAddress->getCountryId() : '';
        $currentCountry = $this->getCurrentCountry() ?? '';

        $storeId = (string)$this->_storeManager->getStore()->getId();
        $widget = CheckoutAPI::get()->promotionalWidgets($storeId)
            ->getAvailableMiniWidgetForProductListingPage(new PromotionalWidgetsCheckoutRequest(
                $shippingCountry,
                $currentCountry
            ));

        return $widget->toArray();
    }

    /**
     * Get current country code
     *
     * @return string
     */
    private function getCurrentCountry(): string
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }
}
