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
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\WidgetTrait;
use Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class AbstractExpressCheckoutBlock
 *
 * Shared base for the cart-centric SeQura Express Checkout storefront buttons (cart page and
 * mini-cart). Builds the availability context from the checkout-session quote and delegates the
 * core call and render-state decision to {@see AvailabilityEvaluator}. Concrete blocks only
 * declare which page they render on.
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
     * @var AvailabilityEvaluator
     */
    private AvailabilityEvaluator $availabilityEvaluator;
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
     * @param AvailabilityEvaluator $availabilityEvaluator
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        Context $context,
        Session $checkoutSession,
        Request $request,
        CustomerSession $customerSession,
        QuoteShippingResolver $shippingResolver,
        AvailabilityEvaluator $availabilityEvaluator
    ) {
        parent::__construct($context);

        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->customerSession = $customerSession;
        $this->shippingResolver = $shippingResolver;
        $this->availabilityEvaluator = $availabilityEvaluator;
    }

    /**
     * Whether the Express Checkout button should render for this page.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->resolveState() === AvailabilityEvaluator::STATE_BUTTON;
    }

    /**
     * Whether the inline "not available" message should render in place of the button
     * (logged in customer whose default shipping country is not supported).
     *
     * @return bool
     */
    public function showUnavailableMessage(): bool
    {
        return $this->resolveState() === AvailabilityEvaluator::STATE_MESSAGE;
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
     * @return string One of AvailabilityEvaluator::STATE_*.
     */
    private function resolveState(): string
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $this->state = AvailabilityEvaluator::STATE_HIDDEN;

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

            $isLoggedIn = $this->customerSession->isLoggedIn();
            $country = $isLoggedIn
                ? (string)$this->shippingResolver->getResolvableShippingCountry($quote)
                : '';

            $this->state = $this->availabilityEvaluator->evaluate(
                (string)$this->_storeManager->getStore()->getId(),
                $this->getExpressCheckoutPage(),
                $isLoggedIn,
                $country,
                $this->getCurrentCurrency(),
                $this->getCustomerIpAddress(),
                $this->getCartProductIds($quote),
                $this->getCartCategoryIds($quote)
            );

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
