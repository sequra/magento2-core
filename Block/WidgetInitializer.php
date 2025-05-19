<?php

namespace Sequra\Core\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;

class WidgetInitializer extends Template
{
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \NumberFormatter
     */
    protected $formatter;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * Constructor
     *
     * @param Context $context
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param Session $checkoutSession
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Template\Context                              $context,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface   $localeResolver,
        Session                                       $checkoutSession,
        array                                         $data = []
    )
    {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
        $this->scopeResolver = $scopeResolver;
        $this->formatter = $this->getFormatter();
        $this->session = $checkoutSession;
    }

    public function getWidgetInitializeData()
    {
        $quote = $this->session->getQuote();
        $shippingCountry = $quote->getShippingAddress()->getCountryId() ?? '';
        $storeId = (string)$this->_storeManager->getStore()->getId();
        $currentCountry = $this->getCurrentCountry() ?? '';

        $widgetInitializeData = CheckoutAPI::get()
            ->promotionalWidgets($storeId)
            ->getPromotionalWidgetInitializeData(
                new PromotionalWidgetsCheckoutRequest($shippingCountry, $currentCountry)
            );

        return $widgetInitializeData->toArray();
    }

    /**
     * Get formatter for currency
     *
     * @return \NumberFormatter
     */
    private function getFormatter()
    {
        $localeCode = $this->localeResolver->getLocale();
        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();
        $currency = $store->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    /**
     * Get decimal separator
     *
     * @return string
     */
    private function getDecimalSeparator()
    {
        return (string)$this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    /**
     * Get a thousand separator
     *
     * @return string
     */
    private function getThousandsSeparator()
    {
        return (string)$this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }

    /**
     * Get current country code
     *
     * @return string
     */
    private function getCurrentCountry()
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Get locale
     *
     * @return string
     */
    private function getLocale()
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }
}
