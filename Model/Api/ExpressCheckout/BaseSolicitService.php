<?php

namespace Sequra\Core\Model\Api\ExpressCheckout;

use Magento\Framework\Exception\LocalizedException;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\ExpressCheckoutSolicitRequest;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

/**
 * Class BaseSolicitService
 *
 * Builds the create-order request for a cart and solicits the SeQura Express Checkout
 * identification form via the integration-core CheckoutAPI.
 */
class BaseSolicitService
{
    /**
     * @var CartProvider
     */
    private CartProvider $cartProvider;
    /**
     * @var CreateOrderRequestBuilderFactory
     */
    private CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory;
    /**
     * @var SeQuraTranslationProvider
     */
    private SeQuraTranslationProvider $translationProvider;
    /**
     * @var QuoteShippingResolver
     */
    private QuoteShippingResolver $shippingResolver;

    /**
     * BaseSolicitService constructor.
     *
     * @param CartProvider $cartProvider
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     * @param SeQuraTranslationProvider $translationProvider
     * @param QuoteShippingResolver $shippingResolver
     */
    public function __construct(
        CartProvider $cartProvider,
        CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory,
        SeQuraTranslationProvider $translationProvider,
        QuoteShippingResolver $shippingResolver
    ) {
        $this->cartProvider = $cartProvider;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
        $this->translationProvider = $translationProvider;
        $this->shippingResolver = $shippingResolver;
    }

    /**
     * Solicits the Express Checkout order and returns the identification form HTML.
     *
     * @param string $cartId Cart ID to solicit the Express Checkout order for
     *
     * @return string Identification form HTML
     *
     * @throws LocalizedException If the order cannot be solicited
     */
    public function solicit(string $cartId): string
    {
        $quote = $this->cartProvider->getQuote($cartId);
        $storeId = (string)$quote->getStore()->getId();

        if (!$this->shippingResolver->resolve($quote)) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.serverError'));
        }

        // @phpstan-ignore-next-line
        $response = CheckoutAPI::get()
            ->expressCheckout($storeId)
            ->solicit(new ExpressCheckoutSolicitRequest($this->createOrderRequestBuilderFactory->create([
                'cartId' => $quote->getId(),
                'storeId' => $storeId,
            ])));

        if (!$response->isSuccessful()) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.serverError'));
        }

        return $response->getIdentificationForm()->getForm();
    }
}
