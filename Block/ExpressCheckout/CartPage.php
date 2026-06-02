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
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\WidgetTrait;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class CartPage
 *
 * Renders the SeQura Express Checkout button on the cart page when the integration-core
 * availability guard allows it for the current logged in customer context. Resolves the
 * customer's default shipping address and the cheapest available shipping rate onto the
 * cart quote so the availability check and downstream solicitation share a consistent
 * country and shipping total.
 */
class CartPage extends Template
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
     * Determines whether the Express Checkout button should render on the cart page.
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
                    ExpressCheckoutPage::cart()->getPage(),
                    $country,
                    $this->getCurrentCurrency(),
                    $this->getCustomerIpAddress()
                )
            );

            return $response->isSuccessful() && !empty($response->toArray()['available']);
        } catch (Exception $e) {
            Logger::logError('Checking Express Checkout availability on cart page failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return false;
        }
    }
}
