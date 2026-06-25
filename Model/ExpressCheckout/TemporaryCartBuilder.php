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
 * for as long as it stays open. Re-soliciting — e.g. cancelling a solicit and changing the
 * quantity — therefore keeps a stable cart reference, so integration-core deletes and re-creates
 * the SeQura order for that cart instead of leaving the cancelled solicit (with the stale
 * quantity) behind. A fresh quote is built only once the previous one has been ordered (and thus
 * deactivated) or no longer exists.
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
        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            // The login state baked into cached pages and the customer-data section are both
            // unreliable, so the server is the authority: 401 tells the storefront to open
            // the login pop-up and retry, while 422 renders the inline "not available" message.
            throw new WebapiException(
                __('Log in to use SeQura Express Checkout.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        $store = $this->storeManager->getStore();
        /** @var Product $product */
        $product = $this->productRepository->getById((int)$productId, false, (int)$store->getId());

        $quote = $this->resolveReusableQuote($customerId, (int)$store->getId());
        if (!$quote) {
            $quote = $this->quoteFactory->create();
            $quote->setStoreId($store->getId());
            $quote->assignCustomer($this->customerSession->getCustomerData());
            $quote->setIsActive(true);
        }

        $result = $quote->addProduct($product, new DataObject($buyRequest));
        if (is_string($result)) {
            // addProduct returns an error message string when the selected options are invalid
            // or the product cannot be added; surface as not eligible so the storefront shows
            // the inline "not available" message instead of a generic server error.
            throw $this->notEligible();
        }

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
        $this->rememberQuoteId($id);

        return $id;
    }

    /**
     * Returns this customer's reusable Express Checkout temporary quote, emptied of its items, or
     * null when there is none to reuse (no remembered id, the quote is gone, it belongs to another
     * customer, or it has already been ordered and deactivated).
     *
     * @param int $customerId
     * @param int $storeId
     *
     * @return Quote|null
     */
    private function resolveReusableQuote(int $customerId, int $storeId): ?Quote
    {
        $storedId = $this->rememberedQuoteId();
        if ($storedId <= 0) {
            return null;
        }

        try {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->get($storedId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        if (!$quote->getIsActive() || (int)$quote->getCustomerId() !== $customerId) {
            return null;
        }

        $quote->setStoreId($storeId);
        $quote->removeAllItems();

        return $quote;
    }

    /**
     * Reads the remembered reusable temporary quote id from the customer session.
     *
     * @return int
     */
    private function rememberedQuoteId(): int
    {
        // Magic session accessor: maps to the `sequra_express_quote_id` storage key.
        // @phpstan-ignore-next-line magic session getter (no PHPStan Magento extension configured)
        $stored = $this->customerSession->getSequraExpressQuoteId();

        return is_scalar($stored) ? (int)$stored : 0;
    }

    /**
     * Remembers the reusable temporary quote id on the customer session.
     *
     * @param int $quoteId
     *
     * @return void
     */
    private function rememberQuoteId(int $quoteId): void
    {
        // Magic session accessor: maps to the `sequra_express_quote_id` storage key.
        // @phpstan-ignore-next-line magic session setter (no PHPStan Magento extension configured)
        $this->customerSession->setSequraExpressQuoteId($quoteId);
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
