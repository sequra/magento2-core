<?php

namespace Sequra\Core\Block\ExpressCheckout;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\ExpressCheckoutAvailabilityRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Responses\ExpressCheckoutAvailabilityResponse;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\WidgetTrait;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class AbstractExpressCheckoutBlock
 *
 * Shared base for the SeQura Express Checkout storefront buttons. Decides — for the
 * current logged in customer and cart — whether the button should render, by resolving
 * the customer's default shipping country and asking the integration-core availability
 * guard for the concrete page. Concrete blocks only declare which page they render on.
 */
abstract class AbstractExpressCheckoutBlock extends Template
{
    use WidgetTrait;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var QuoteShippingResolver
     */
    private QuoteShippingResolver $shippingResolver;

    /**
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param Context $context
     * @param Session $checkoutSession
     * @param Request $request
     * @param CustomerSession $customerSession
     * @param QuoteShippingResolver $shippingResolver
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        Context $context,
        Session $checkoutSession,
        Request $request,
        CustomerSession $customerSession,
        QuoteShippingResolver $shippingResolver
    ) {
        parent::__construct($context);

        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->customerSession = $customerSession;
        $this->shippingResolver = $shippingResolver;
    }

    /**
     * Determines whether the Express Checkout button should render for this page.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || (int)$quote->getItemsCount() < 1) {
                return false;
            }

            $country = $this->shippingResolver->getResolvableShippingCountry($quote);
            if ($country === null) {
                return false;
            }

            $storeId = (string)$this->_storeManager->getStore()->getId();

            /** @var ExpressCheckoutAvailabilityResponse $response */
            $response = CheckoutAPI::get()->expressCheckout($storeId)->isAvailable(
                new ExpressCheckoutAvailabilityRequest(
                    $this->getExpressCheckoutPage(),
                    $country,
                    $this->getCurrentCurrency(),
                    $this->getCustomerIpAddress()
                )
            );

            return $response->isSuccessful() && !empty($response->toArray()['available']);
        } catch (Exception $e) {
            Logger::logError('Checking Express Checkout availability failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return false;
        }
    }

    /**
     * Returns the integration-core page identifier this block renders on
     * (e.g. 'cart', 'mini-cart').
     *
     * @return string
     */
    abstract protected function getExpressCheckoutPage(): string;
}
