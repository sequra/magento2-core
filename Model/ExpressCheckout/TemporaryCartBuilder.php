<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class TemporaryCartBuilder
 *
 * Builds a detached temporary quote for the SeQura Express Checkout product flow: a standalone
 * quote owned by the logged in customer containing only the viewed product with its selected
 * options and quantity. The shopper's real cart is never touched, so cancelling the express
 * purchase needs no restore.
 *
 * A single temporary quote is reused per customer (its id is remembered on the customer session)
 * for as long as it stays an open draft. Re-soliciting — e.g. cancelling a solicit and changing
 * the quantity — therefore keeps a stable cart reference, so integration-core deletes and
 * re-creates the SeQura order for that cart instead of leaving the cancelled solicit (with the
 * stale quantity) behind. A fresh quote is built only once the previous one has been placed as an
 * order or no longer exists.
 *
 * The draft is kept inactive between solicits ({@see deactivate}) so it never shadows the
 * shopper's real cart in active-cart resolution (cart page / mini-cart) after they cancel; it is
 * activated only for the duration of a solicit and again at order placement.
 */
class TemporaryCartBuilder
{
    /**
     * HTTP status returned when the request is not eligible for Express Checkout (invalid
     * options or a virtual quote), surfaced by the storefront as the inline message. Guest
     * callers get HTTP 401 instead, which the storefront answers with the login pop-up.
     */
    private const HTTP_NOT_ELIGIBLE = 422;

    /**
     * Customer-session key holding the reusable draft id for the product-page flow.
     */
    private const DRAFT_KEY_PRODUCT = 'sequra_express_quote_id_product';

    /**
     * Customer-session key holding the reusable draft id for the cart / mini-cart flow. Kept
     * separate from the product key so a solicit from one surface never wipes and refills the
     * draft an outstanding SeQura form from the other surface still references.
     */
    private const DRAFT_KEY_CART = 'sequra_express_quote_id_cart';

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    /**
     * @var QuoteFactory
     */
    private QuoteFactory $quoteFactory;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * TemporaryCartBuilder constructor.
     *
     * @param CustomerSession $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param QuoteFactory $quoteFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CustomerSession $customerSession,
        ProductRepositoryInterface $productRepository,
        QuoteFactory $quoteFactory,
        CartRepositoryInterface $quoteRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Builds and persists the temporary quote, returning its ID.
     *
     * @param string $productId Entity ID of the product to purchase.
     * @param mixed[] $buyRequest Add-to-cart buy request (qty + option arrays).
     *
     * @return int Temporary quote ID.
     *
     * @throws WebapiException If the caller is a guest (HTTP 401) or the quote is virtual (HTTP 422).
     * @throws NoSuchEntityException If the product does not exist.
     * @throws LocalizedException If the product cannot be added to the quote.
     */
    public function build(string $productId, array $buyRequest): int
    {
        $customerId = $this->assertLoggedIn();

        $store = $this->storeManager->getStore();
        /** @var Product $product */
        $product = $this->productRepository->getById((int)$productId, false, (int)$store->getId());

        $quote = $this->createOrReuseQuote($customerId, (int)$store->getId(), self::DRAFT_KEY_PRODUCT);

        $result = $quote->addProduct($product, new DataObject($buyRequest));
        if (is_string($result)) {
            // addProduct returns an error message string when the selected options are invalid
            // or the product cannot be added; surface as not eligible so the storefront shows
            // the inline "not available" message instead of a generic server error.
            throw $this->notEligible();
        }

        return $this->finalizeQuote($quote, self::DRAFT_KEY_PRODUCT);
    }

    /**
     * Builds and persists a detached temporary quote cloning the given source cart, returning its
     * ID. Used by the cart / mini-cart Express Checkout flow so the shopper's real cart is never
     * mutated by the solicit (address import, forced payment method, added shipping line);
     * cancelling the express purchase therefore leaves the real cart untouched.
     *
     * The clone reproduces the cart the shopper saw: line items (with their options), the applied
     * coupon, and the selected shipping method — so the solicited/placed total matches the cart
     * rather than silently dropping a discount or downgrading shipping to the cheapest rate.
     *
     * @param Quote $source Cart quote whose contents are cloned into the temporary quote.
     *
     * @return int Temporary quote ID.
     *
     * @throws WebapiException If the caller is a guest (HTTP 401), the cart is empty or the quote
     *                         is virtual (HTTP 422).
     * @throws NoSuchEntityException If a source item's product no longer exists.
     * @throws LocalizedException If an item cannot be added to the quote.
     */
    public function buildFromQuote(Quote $source): int
    {
        $customerId = $this->assertLoggedIn();

        $items = $source->getAllVisibleItems();
        if (empty($items)) {
            // Empty/expired cart (e.g. the last item was removed in another tab): there is nothing
            // to purchase, so reject rather than solicit a zero-total order.
            throw $this->notEligible();
        }

        $storeId = (int)$source->getStoreId();
        $quote = $this->createOrReuseQuote($customerId, $storeId, self::DRAFT_KEY_CART);

        foreach ($items as $item) {
            /** @var Product $product */
            // @phpstan-ignore-next-line getProductId() is a magic DataObject getter
            $product = $this->productRepository->getById((int)$item->getProductId(), false, $storeId);
            // Re-add through the stored buy request so configurable/bundle/grouped selections and
            // custom options are preserved exactly as in the source cart.
            $result = $quote->addProduct($product, $item->getBuyRequest());
            if (is_string($result)) {
                throw $this->notEligible();
            }
        }

        // Carry the coupon so cart-rule discounts reapply on collectTotals (collectTotals is run in
        // finalizeQuote); without it the clone would be solicited/placed at the full price.
        $quote->setCouponCode((string)$source->getCouponCode());
        // Carry the shopper's selected shipping method so resolve() keeps it (it captures the
        // preselected method before re-collecting rates) instead of downgrading to the cheapest.
        $quote->getShippingAddress()->setShippingMethod(
            (string)$source->getShippingAddress()->getShippingMethod()
        );

        return $this->finalizeQuote($quote, self::DRAFT_KEY_CART);
    }

    /**
     * Asserts a logged in customer and returns the id.
     *
     * The login state baked into cached pages and the customer-data section are both unreliable,
     * so the server is the authority: 401 tells the storefront to open the login pop-up and retry.
     *
     * @return int
     *
     * @throws WebapiException When the caller is a guest (HTTP 401).
     */
    private function assertLoggedIn(): int
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            throw new WebapiException(
                __('Log in to use SeQura Express Checkout.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        return $customerId;
    }

    /**
     * Finalizes a draft: recomputes totals, enforces the shippable guard, saves and remembers its id.
     *
     * Shared finalize tail for both build entry points.
     *
     * @param Quote $quote
     * @param string $draftKey One of self::DRAFT_KEY_*.
     *
     * @return int Temporary quote ID.
     *
     * @throws WebapiException When the resulting quote is virtual (HTTP 422).
     */
    private function finalizeQuote(Quote $quote, string $draftKey): int
    {
        $quote->setData('totals_collected_flag', false);
        $quote->collectTotals();

        // Virtual/downloadable simples and bundle/grouped selections that yield a virtual item
        // are uniformly rejected here (SeQura requires a shippable order).
        if ($this->hasVirtualItem($quote)) {
            throw $this->notEligible();
        }

        $this->quoteRepository->save($quote);

        $quoteId = $quote->getId();
        $id = is_scalar($quoteId) ? (int)$quoteId : 0;
        $this->rememberQuoteId($id, $draftKey);

        return $id;
    }

    /**
     * Returns the customer's reusable draft (reactivated, emptied) or a fresh detached quote.
     *
     * @param int $customerId
     * @param int $storeId
     * @param string $draftKey One of self::DRAFT_KEY_*.
     *
     * @return Quote
     */
    private function createOrReuseQuote(int $customerId, int $storeId, string $draftKey): Quote
    {
        $quote = $this->resolveReusableQuote($customerId, $storeId, $draftKey);
        if (!$quote) {
            $quote = $this->quoteFactory->create();
            $quote->setStoreId($storeId);
            $quote->assignCustomer($this->customerSession->getCustomerData());
            $quote->setIsActive(true);
        }

        return $quote;
    }

    /**
     * Returns this customer's reusable Express Checkout temporary quote, reactivated and emptied of
     * its items, or null when there is none to reuse (no remembered id, the quote is gone, it
     * belongs to another customer, or it has already been placed as an order).
     *
     * @param int $customerId
     * @param int $storeId
     * @param string $draftKey One of self::DRAFT_KEY_*.
     *
     * @return Quote|null
     */
    private function resolveReusableQuote(int $customerId, int $storeId, string $draftKey): ?Quote
    {
        $storedId = $this->rememberedQuoteId($draftKey);
        if ($storedId <= 0) {
            return null;
        }

        try {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->get($storedId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        // A reserved order id is only set when the quote goes through placeOrder, so an empty value
        // means the quote is still an open express draft that can be reused. (Soliciting never
        // reserves an order id.) The is_active flag is not a reuse signal here: drafts are left
        // inactive between solicits so they do not shadow the real cart.
        //
        // A different store is not reusable: the draft's currency codes (quote/base/store) are
        // fixed at creation and setStoreId() would not reset them, so a draft from another store
        // would carry the wrong currency here (multi-store, multi-currency, same session). Rebuild
        // fresh for the current store instead.
        if ((int)$quote->getCustomerId() !== $customerId
            || (string)$quote->getReservedOrderId() !== ''
            || (int)$quote->getStoreId() !== $storeId
        ) {
            return null;
        }

        $quote->setIsActive(true);
        $quote->removeAllItems();

        return $quote;
    }

    /**
     * Deactivates the temporary quote once a solicit is done with it, so it stays out of
     * active-cart resolution (cart page / mini-cart) and never shadows the shopper's real cart.
     * It is reactivated on the next solicit ({@see resolveReusableQuote}) and at order placement.
     *
     * @param int $cartId
     *
     * @return void
     */
    public function deactivate(int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        try {
            $quote = $this->quoteRepository->get($cartId);
        } catch (NoSuchEntityException $e) {
            return;
        }

        if ($quote->getIsActive()) {
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        }
    }

    /**
     * Reads the remembered reusable temporary quote id for the given flow from the customer session.
     *
     * @param string $draftKey One of self::DRAFT_KEY_*.
     *
     * @return int
     */
    private function rememberedQuoteId(string $draftKey): int
    {
        $stored = $this->customerSession->getData($draftKey);

        return is_scalar($stored) ? (int)$stored : 0;
    }

    /**
     * Remembers the reusable temporary quote id for the given flow on the customer session.
     *
     * @param int $quoteId
     * @param string $draftKey One of self::DRAFT_KEY_*.
     *
     * @return void
     */
    private function rememberQuoteId(int $quoteId, string $draftKey): void
    {
        // @phpstan-ignore-next-line magic method forwarded to Storage via SessionManager::__call
        $this->customerSession->setData($draftKey, $quoteId);
    }

    /**
     * Whether the quote is fully virtual or contains any virtual item.
     *
     * @param Quote $quote
     *
     * @return bool
     */
    private function hasVirtualItem(Quote $quote): bool
    {
        if ($quote->isVirtual()) {
            return true;
        }

        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getIsVirtual()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the HTTP 422 exception the storefront turns into the inline
     * "not available" message.
     *
     * @return WebapiException
     */
    private function notEligible(): WebapiException
    {
        return new WebapiException(
            __('SeQura Express Checkout is not available for this account.'),
            0,
            self::HTTP_NOT_ELIGIBLE
        );
    }
}
