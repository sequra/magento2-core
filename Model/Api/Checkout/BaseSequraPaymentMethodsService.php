<?php

namespace Sequra\Core\Model\Api\Checkout;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\Solicitation\Controller\SolicitationController;
use SeQura\Core\BusinessLogic\CheckoutAPI\Solicitation\Requests\SolicitationRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\Solicitation\Response\IdentificationFormResponse;
use SeQura\Core\BusinessLogic\CheckoutAPI\Solicitation\Response\SolicitationResponse;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

class BaseSequraPaymentMethodsService
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory
     */
    private $createOrderRequestBuilderFactory;
    /**
     * @var Json
     */
    private $jsonSerializer;
    /**
     * @var CartProvider
     */
    private $cartProvider;
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * BaseSequraPaymentMethodsService constructor.
     * @param Http $request
     * @param Json $jsonSerializer
     * @param CartProvider $cartProvider
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     * @param SeQuraTranslationProvider $translationProvider
     */
    public function __construct(
        Http $request,
        Json $jsonSerializer,
        CartProvider $cartProvider,
        \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory,
        SeQuraTranslationProvider $translationProvider
    ) {
        $this->request = $request;
        $this->jsonSerializer = $jsonSerializer;
        $this->cartProvider = $cartProvider;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
        $this->translationProvider = $translationProvider;
    }

    /**
     * Returns available payment methods for the given cart
     *
     * @param string $cartId Cart ID to get payment methods for
     *
     * @return array<array<string, string>> Available payment methods
     */
    public function getAvailablePaymentMethods(string $cartId): array
    {
        $quote = $this->cartProvider->getQuote($cartId);

        if (empty($quote->getShippingAddress()->getCountryId())) {
            return [];
        }

        /** @var SolicitationController $solicitationController */
        $solicitationController = CheckoutAPI::get()->solicitation((string)$quote->getStore()->getId());
        /** @var SolicitationResponse $response */
        $response = $solicitationController->solicitFor($this->createSolicitationRequest($quote));

        if (!$response->isSuccessful() || $response->getSolicitedOrder() === null) {
            return [];
        }

        /** @var array<array<string, string>> $paymentMethods */
        $paymentMethods = $response->toArray()['availablePaymentMethods'];

        return $paymentMethods;
    }

    /**
     * Gets the payment form for given cart ID
     *
     * @param string $cartId Cart ID to get form for
     *
     * @return string Payment form HTML
     * @throws LocalizedException If form cannot be retrieved
     */
    public function getForm(string $cartId): string
    {
        $quote = $this->cartProvider->getQuote($cartId);

        /** @var SolicitationController $solicitationController */
        $solicitationController = CheckoutAPI::get()->solicitation((string)$quote->getStore()->getId());
        /** @var SolicitationResponse $response */
        $response = $solicitationController->solicitFor($this->createSolicitationRequest($quote));

        if (!$response->isSuccessful()) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.serverError'));
        }

        if ($response->getSolicitedOrder() === null) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.notAvailable'));
        }

        $payload = [];
        if (!empty($this->request->getContent())) {
            $payload = $this->jsonSerializer->unserialize($this->request->getContent());
        }

        $product = !empty($payload['product_data']['sequra_product']) ?
            $payload['product_data']['sequra_product'] : null;
        $campaign = !empty($payload['product_data']['sequra_campaign']) ?
            $payload['product_data']['sequra_campaign'] : null;

        $quoteId = $quote->getId();
        /** @var IdentificationFormResponse $formResponse */
        $formResponse = $solicitationController->getIdentificationForm(
            is_scalar($quoteId) ? (string)$quoteId : '',
            $product,
            $campaign
        );

        if (!$formResponse->isSuccessful()) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.serverError'));
        }

        return $formResponse->getIdentificationForm()->getForm();
    }

    /**
     * Wraps the order builder of the quote together with the products it holds.
     *
     * The product IDs let integration-core apply the product and category exclusions the merchant configured.
     *
     * @param Quote $quote
     *
     * @return SolicitationRequest
     */
    private function createSolicitationRequest(Quote $quote): SolicitationRequest
    {
        /** @var CreateOrderRequestBuilder $builder */
        $builder = $this->createOrderRequestBuilderFactory->create([
            'cartId' => $quote->getId(),
            'storeId' => (string)$quote->getStore()->getId(),
        ]);

        return new SolicitationRequest($builder, $this->getCartProductIds($quote));
    }

    /**
     * Returns the IDs of the products in the quote, as strings.
     *
     * Child items are included, so the variant of a configurable product and the products of a bundle are checked
     * against the exclusions together with their parent.
     *
     * @param Quote $quote
     *
     * @return string[]
     */
    private function getCartProductIds(Quote $quote): array
    {
        $productIds = [];
        foreach ($quote->getAllItems() as $item) {
            $product = $item->getProduct();
            $productId = $product ? (string)$product->getId() : '';
            if ($productId !== '') {
                $productIds[$productId] = $productId;
            }
        }

        return array_values($productIds);
    }
}
