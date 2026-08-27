<?php

namespace Sequra\Core\Model\Api\ExpressCheckout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\ExpressCheckoutSolicitRequest;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Model\ExpressCheckout\CartSummaryFormDecorator;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class BaseSolicitService
 *
 * Builds the create-order request for a cart and solicits the SeQura Express Checkout
 * identification form via the integration-core CheckoutAPI.
 *
 * {@see update} is the same flow run again after the shopper changes something on the SeQura
 * CartSummary page: it mutates the solicited quote first and answers with the refreshed
 * cartDataReady payload instead of the form HTML. Re-soliciting is supported as-is — core's
 * OrderService::solicitFor drops the stored order row and re-stores whatever comes back.
 */
class BaseSolicitService
{
    /**
     * HTTP status returned when the logged in customer is not eligible for Express Checkout
     * (unsupported delivery country, or an address with no usable shipping rate), so the
     * storefront can show a specific "not available" message instead of a generic server error.
     * Having no address at all is NOT one of these: that is precisely what the express screen
     * collects, so the solicit goes out with the addresses missing.
     */
    private const HTTP_NOT_ELIGIBLE = 422;

    /**
     * Customer-session key holding the id of the quote the last Express Checkout solicit ran
     * against. The cart-update endpoint re-solicits that same quote and never the live cart:
     * both express flows solicit a detached draft built by TemporaryCartBuilder, and the client
     * is never told which one.
     */
    public const SESSION_KEY_SOLICITED_QUOTE = 'sequra_express_solicited_quote_id';

    /**
     * @var CartProvider
     */
    private CartProvider $cartProvider;
    /**
     * @var CreateOrderRequestBuilderFactory
     */
    private CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory;
    /**
     * @var QuoteShippingResolver
     */
    private QuoteShippingResolver $shippingResolver;
    /**
     * @var CartSummaryFormDecorator
     */
    private CartSummaryFormDecorator $cartSummaryFormDecorator;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * BaseSolicitService constructor.
     *
     * @param CartProvider $cartProvider
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     * @param QuoteShippingResolver $shippingResolver
     * @param CartSummaryFormDecorator $cartSummaryFormDecorator
     * @param CartRepositoryInterface $quoteRepository
     * @param CustomerSession $customerSession
     */
    public function __construct(
        CartProvider $cartProvider,
        CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory,
        QuoteShippingResolver $shippingResolver,
        CartSummaryFormDecorator $cartSummaryFormDecorator,
        CartRepositoryInterface $quoteRepository,
        CustomerSession $customerSession
    ) {
        $this->cartProvider = $cartProvider;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
        $this->shippingResolver = $shippingResolver;
        $this->cartSummaryFormDecorator = $cartSummaryFormDecorator;
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession;
    }

    /**
     * Solicits the Express Checkout order and returns the identification form HTML.
     *
     * @param string $cartId Cart ID to solicit the Express Checkout order for
     *
     * @return string Identification form HTML
     *
     * @throws WebapiException If the customer is not eligible for Express Checkout (HTTP 422)
     * @throws LocalizedException If the order cannot be solicited
     */
    public function solicit(string $cartId): string
    {
        $quote = $this->cartProvider->getQuote($cartId);

        if (!$this->shippingResolver->resolve($quote)) {
            throw $this->notEligible();
        }

        $form = $this->solicitForm($quote);
        $this->rememberSolicitedQuote($quote);

        // Express Checkout V1 spike (PAR-835): open the form on the CartSummary page and feed
        // it the shipping data via the cartDataReady postMessage.
        return $this->cartSummaryFormDecorator->decorate($form, $quote);
    }

    /**
     * Applies a CartSummary change, re-solicits and returns the refreshed cartDataReady payload.
     *
     * The change lands on the quote the last solicit ran against, never on the live cart.
     *
     * Same flow as {@see solicit} — resolve shipping, solicit through the CheckoutAPI, decorate —
     * with the customer-defaults resolution swapped for the shopper's own change and the payload
     * returned as data instead of wrapped in HTML.
     *
     * @param mixed[] $change Validated change: address, shippingMethodReference, email.
     *
     * @return array<string, mixed> Refreshed cartDataReady payload.
     *
     * @throws WebapiException Not eligible (HTTP 422) or an unavailable carrier reference (HTTP 400)
     * @throws NoSuchEntityException If there is no solicited cart in the session, or it is gone
     * @throws LocalizedException If the quote cannot be saved or the order cannot be solicited
     */
    public function update(array $change): array
    {
        $quote = $this->getSolicitedQuote();

        if (!$this->shippingResolver->applyChange($quote, $change)) {
            throw $this->notEligible();
        }

        return $this->cartSummaryFormDecorator->buildCartData($this->solicitForm($quote), $quote);
    }

    /**
     * Solicits the SeQura order for an already-prepared quote, returning the raw form HTML.
     *
     * @param Quote $quote Quote with its address, shipping method and totals already settled.
     *
     * @return string
     *
     * @throws WebapiException If SeQura cannot produce an identification form (HTTP 422)
     */
    private function solicitForm(Quote $quote): string
    {
        $storeId = (string)$quote->getStore()->getId();

        // The `true` flag enables core's country check: an unsupported delivery country yields
        // an unsuccessful response (no exception, nothing logged) instead of the solicit
        // hard-failing on the missing merchant.
        // @phpstan-ignore-next-line
        $response = CheckoutAPI::get()
            ->expressCheckout($storeId)
            ->solicit(new ExpressCheckoutSolicitRequest($this->createOrderRequestBuilderFactory->create([
                'cartId' => $quote->getId(),
                'storeId' => $storeId,
            ]), true));

        if (!$response->isSuccessful()) {
            // An unsuccessful solicit means SeQura cannot produce an identification form for
            // this customer/cart — most commonly the unavailable response core returns (without
            // logging) when the customer's default address country has no configured merchant.
            // The shopper has no way to recover, so surface it as the "not eligible" 422 the
            // storefront turns into the inline unavailable message.
            throw $this->notEligible();
        }

        return $response->getIdentificationForm()->getForm();
    }

    /**
     * Loads the quote the last solicit ran against, as recorded on the customer session.
     *
     * Loaded by id rather than as the active cart: express drafts are left inactive between
     * solicits so they never shadow the shopper's real cart.
     *
     * @return Quote
     *
     * @throws NoSuchEntityException When no solicit has run in this session, or its quote is gone.
     */
    private function getSolicitedQuote(): Quote
    {
        $stored = $this->customerSession->getData(self::SESSION_KEY_SOLICITED_QUOTE);
        $quoteId = is_scalar($stored) ? (int)$stored : 0;
        if ($quoteId <= 0) {
            throw new NoSuchEntityException(__('No solicited SeQura Express Checkout cart in session.'));
        }

        /** @var Quote $quote */
        $quote = $this->quoteRepository->get($quoteId);

        return $quote;
    }

    /**
     * Records the solicited quote on the customer session, for the cart-update endpoint to reuse.
     *
     * Server-side only: the client never sees nor supplies a cart id.
     *
     * @param Quote $quote
     *
     * @return void
     */
    private function rememberSolicitedQuote(Quote $quote): void
    {
        $quoteId = $quote->getId();
        // @phpstan-ignore-next-line magic method forwarded to Storage via SessionManager::__call
        $this->customerSession->setData(
            self::SESSION_KEY_SOLICITED_QUOTE,
            is_scalar($quoteId) ? (int)$quoteId : 0
        );
    }

    /**
     * Builds the HTTP 422 exception the storefront turns into the inline
     * "SeQura is not available for your account" message.
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
