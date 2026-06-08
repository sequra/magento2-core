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
 * purchase needs no restore. Orphaned temporary quotes are cleaned up later (deferred).
 */
class TemporaryCartBuilder
{
    /**
     * HTTP status returned when the request is not eligible for Express Checkout (guest caller
     * or the resulting quote is virtual), surfaced by the storefront as the inline message.
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
     * @param array<string, mixed> $buyRequest Add-to-cart buy request (qty + option arrays).
     *
     * @return int Temporary quote ID.
     *
     * @throws WebapiException If the caller is a guest or the resulting quote is virtual (HTTP 422).
     * @throws NoSuchEntityException If the product does not exist.
     * @throws LocalizedException If the product cannot be added to the quote.
     */
    public function build(string $productId, array $buyRequest): int
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            throw $this->notEligible();
        }

        $store = $this->storeManager->getStore();
        /** @var Product $product */
        $product = $this->productRepository->getById((int)$productId, false, (int)$store->getId());

        $quote = $this->quoteFactory->create();
        $quote->setStoreId($store->getId());
        $quote->assignCustomer($this->customerSession->getCustomerData());
        $quote->setIsActive(true);

        $result = $quote->addProduct($product, new DataObject($buyRequest));
        if (is_string($result)) {
            // addProduct returns an error message string when the selected options are invalid
            // or the product cannot be added; surface as not eligible so the storefront shows
            // the inline "not available" message instead of a generic server error.
            throw $this->notEligible();
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        // Virtual/downloadable simples and bundle/grouped selections that yield a virtual item
        // are uniformly rejected here (SeQura requires a shippable order).
        if ($this->hasVirtualItem($quote)) {
            throw $this->notEligible();
        }

        $this->quoteRepository->save($quote);

        return (int)$quote->getId();
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
