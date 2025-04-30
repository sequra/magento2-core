<?php

namespace Sequra\Core\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\Session as CheckoutSession;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
use Sequra\Core\Block\Widget\WidgetTrait;
use Magento\Framework\View\Element\Template;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Sequra\Core\Gateway\Validator\CartWidgetAvailabilityValidator;

/**
 * Implements required logic to show widgets in the cart page
 */
class Cart extends Template
{
    use WidgetTrait;

     /**
      * @var \Magento\Framework\App\ScopeResolverInterface
      */
    protected $scopeResolver;

    /**
     * @var CartWidgetAvailabilityValidator
     */
    private $cartWidgetAvailabilityValidator;

    /**
     * Constructor
     *
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param CurrencyValidator $currencyValidator
     * @param IpAddressValidator $ipAddressValidator
     * @param CartWidgetAvailabilityValidator $cartWidgetAvailabilityValidator
     * @param Context $context
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        CurrencyValidator $currencyValidator,
        IpAddressValidator $ipAddressValidator,
        CartWidgetAvailabilityValidator $cartWidgetAvailabilityValidator,
        Context $context
    ) {
        parent::__construct($context);
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
        $this->cartWidgetAvailabilityValidator = $cartWidgetAvailabilityValidator;
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
            // TODO: Log currency error
            return '';
        }

        if (!$this->ipAddressValidator->validate($subject)->isValid()) {
            // TODO: Log IP error
            return '';
        }

        if (!$this->cartWidgetAvailabilityValidator->validate($subject)->isValid()) {
            // TODO: Log cart widget error
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Prepare the list of available widgets to show in the frontend based on the configuration and the current context
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
     *      maxAmount: int|null
     *  }>
     */
    public function getAvailableWidgets()
    {
        $widgets = [];

        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            return $widgets;
        }

        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();

        /** @var CachedPaymentMethodsResponse $cachedPaymentMethods */
        $cachedPaymentMethods = CheckoutAPI::get()->cachedPaymentMethods($store->getStoreId())
            ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($merchantId));

        $destinationSelector = '.cart-totals';
        $priceSelector = ".grand.totals .price";

        /**
         * @var string|null $theme
         */
        $theme = null;
        $settings = $this->getWidgetSettings();
        if ($settings) {
            $theme = $settings->getWidgetConfig();
        }
        
        foreach ($cachedPaymentMethods->toArray() as $paymentMethod) {
            if (!is_array($paymentMethod) || !isset($paymentMethod['product']) || $paymentMethod['product'] !== 'pp3') {
                continue;
            }

            $widgets[] = [
                'product' => (string) $paymentMethod['product'],
                'campaign' => (string) $paymentMethod['campaign'],
                'priceSel' => $priceSelector,
                'dest' => $destinationSelector,
                'theme' => $theme,
                'reverse' => "0",
                'minAmount' => isset($paymentMethod['minAmount']) ? (int) $paymentMethod['minAmount'] : 0,
                'maxAmount' => isset($paymentMethod['maxAmount']) ? (int) $paymentMethod['maxAmount'] : null,
            ];

            break;
        }
        return $widgets;
    }
}
