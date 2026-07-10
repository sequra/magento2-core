<?php

namespace Sequra\Core\Model\Api\ExpressCheckout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception as WebapiException;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\ExpressCheckoutSolicitRequest;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class BaseSolicitService
 *
 * Builds the create-order request for a cart and solicits the SeQura Express Checkout
 * identification form via the integration-core CheckoutAPI.
 */
class BaseSolicitService
{
    /**
     * HTTP status returned when the logged in customer is not eligible for Express Checkout
     * (no supported default shipping address / unsupported country), so the storefront can
     * show a specific "not available" message instead of a generic server error.
     */
    private const HTTP_NOT_ELIGIBLE = 422;

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
     * BaseSolicitService constructor.
     *
     * @param CartProvider $cartProvider
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     * @param QuoteShippingResolver $shippingResolver
     */
    public function __construct(
        CartProvider $cartProvider,
        CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory,
        QuoteShippingResolver $shippingResolver
    ) {
        $this->cartProvider = $cartProvider;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
        $this->shippingResolver = $shippingResolver;
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
        $storeId = (string)$quote->getStore()->getId();

        if (!$this->shippingResolver->resolve($quote)) {
            throw $this->notEligible();
        }

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
