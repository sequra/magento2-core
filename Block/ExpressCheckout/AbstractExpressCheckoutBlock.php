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
use Magento\Quote\Model\Quote;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\ExpressCheckoutAvailabilityRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\GuestExpressCheckoutAvailabilityRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Responses\ExpressCheckoutAvailabilityResponse;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Responses\GuestExpressCheckoutAvailabilityResponse;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\WidgetTrait;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class AbstractExpressCheckoutBlock
 *
 * Shared base for the SeQura Express Checkout storefront buttons. Decides — per page and per
 * storefront context — whether to render the button, an inline "not available" message, or
 * nothing at all, by asking the integration-core availability guard:
 *  - logged in customer: the per-country check, using the customer's default shipping country.
 *    Not available (or no resolvable country) replaces the button with the inline message.
 *  - guest: the country-agnostic guest check. Not available simply renders nothing; the
 *    customer's actual country is validated after login at solicit time (HTTP 422 backstop).
 * Concrete blocks only declare which page they render on.
 */
abstract class AbstractExpressCheckoutBlock extends Template
{
    use WidgetTrait;

    /**
     * Render states returned by {@see resolveState()}.
     */
    private const STATE_BUTTON = 'button';
    private const STATE_MESSAGE = 'message';
    private const STATE_HIDDEN = 'hidden';

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var QuoteShippingResolver
     */
    private QuoteShippingResolver $shippingResolver;
    /**
     * Memoized render state for the current request.
     *
     * @var string|null
     */
    private ?string $state = null;

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
     * Whether the Express Checkout button should render for this page.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->resolveState() === self::STATE_BUTTON;
    }

    /**
     * Whether the inline "not available" message should render in place of the button
     * (logged in customer whose default shipping country is not supported).
     *
     * @return bool
     */
    public function showUnavailableMessage(): bool
    {
        return $this->resolveState() === self::STATE_MESSAGE;
    }

    /**
     * The inline message shown when Express Checkout is unavailable for the logged in customer.
     *
     * @return string
     */
    public function getUnavailableMessage(): string
    {
        return (string)__('SeQura is not available for your account.');
    }

    /**
     * Resolves — once per request — whether to render the button, the inline message or nothing.
     *
     * @return string One of self::STATE_*.
     */
    private function resolveState(): string
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $this->state = self::STATE_HIDDEN;

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || (int)$quote->getItemsCount() < 1) {
                return $this->state;
            }

            // SeQura is unavailable when the cart contains a virtual product, mirroring the
            // regular checkout guard (CreateOrderRequestBuilder::isAllowedFor). Render nothing.
            if ($this->hasVirtualItem($quote)) {
                return $this->state;
            }

            $storeId = (string)$this->_storeManager->getStore()->getId();
            $page = $this->getExpressCheckoutPage();
            $currency = $this->getCurrentCurrency();
            $ipAddress = $this->getCustomerIpAddress();
            $productIds = $this->getCartProductIds($quote);
            $categoryIds = $this->getCartCategoryIds($quote);

            if ($this->customerSession->isLoggedIn()) {
                $country = (string)$this->shippingResolver->getResolvableShippingCountry($quote);

                /** @var ExpressCheckoutAvailabilityResponse $response */
                $response = CheckoutAPI::get()->expressCheckout($storeId)->isAvailable(
                    new ExpressCheckoutAvailabilityRequest(
                        $page,
                        $currency,
                        $ipAddress,
                        $country,
                        $productIds,
                        $categoryIds
                    )
                );

                $available = $response->isSuccessful() && !empty($response->toArray()['available']);
                $this->state = $available ? self::STATE_BUTTON : self::STATE_MESSAGE;

                return $this->state;
            }

            /** @var GuestExpressCheckoutAvailabilityResponse $response */
            $response = CheckoutAPI::get()->expressCheckout($storeId)->isAvailableForGuest(
                new GuestExpressCheckoutAvailabilityRequest(
                    $page,
                    $currency,
                    $ipAddress,
                    $productIds,
                    $categoryIds
                )
            );

            if ($response->isSuccessful() && !empty($response->toArray()['available'])) {
                $this->state = self::STATE_BUTTON;
            }

            return $this->state;
        } catch (Exception $e) {
            Logger::logError('Checking Express Checkout availability failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return $this->state;
        }
    }

    /**
     * Whether the cart contains at least one virtual product. SeQura is not offered for virtual
     * items (parity with the regular checkout availability check), so the button must not render.
     *
     * @param Quote $quote
     *
     * @return bool
     */
    private function hasVirtualItem(Quote $quote): bool
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getIsVirtual()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collects the cart's product entity IDs for the per-product eligibility guard.
     *
     * @param Quote $quote
     *
     * @return string[]
     */
    private function getCartProductIds(Quote $quote): array
    {
        $productIds = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $productId = $product ? (string)$product->getId() : '';
            if ($productId !== '') {
                $productIds[$productId] = $productId;
            }
        }

        return array_values($productIds);
    }

    /**
     * Collects the category IDs of every product in the cart for the per-category eligibility guard.
     *
     * @param Quote $quote
     *
     * @return string[]
     */
    private function getCartCategoryIds(Quote $quote): array
    {
        $categoryIds = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                continue;
            }

            foreach ($product->getCategoryIds() as $categoryId) {
                $categoryId = (string)$categoryId;
                if ($categoryId !== '') {
                    $categoryIds[$categoryId] = $categoryId;
                }
            }
        }

        return array_values($categoryIds);
    }

    /**
     * Returns the integration-core page identifier this block renders on
     * (e.g. 'cart', 'mini-cart').
     *
     * @return string
     */
    abstract protected function getExpressCheckoutPage(): string;
}
