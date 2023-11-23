<?php

namespace Sequra\Core\Model\Api\Checkout;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

/**
 * Class BaseSequraPaymentMethodsService
 *
 * @package Sequra\Core\Model\Api\Checkout
 */
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

    public function getAvailablePaymentMethods(string $cartId): array
    {
        $quote = $this->cartProvider->getQuote($cartId);

        if (empty($quote->getShippingAddress()->getCountryId())) {
            return [];
        }

        /** @var CreateOrderRequestBuilder $builder */
        $builder = $this->createOrderRequestBuilderFactory->create([
            'cartId' => $quote->getId(),
            'storeId' => (string)$quote->getStore()->getId(),
        ]);

        $generalSettings = AdminAPI::get()->generalSettings((string)$quote->getStore()->getId())->getGeneralSettings();
        if (!$generalSettings->isSuccessful() || !$builder->isAllowedFor($generalSettings)) {
            return [];
        }

        $response = CheckoutAPI::get()
            ->solicitation((string)$quote->getStore()->getId())
            ->solicitFor($builder);

        if (!$response->isSuccessful()) {
            return [];
        }

        return $response->toArray()['availablePaymentMethods'];
    }

    public function getForm(string $cartId): string
    {
        $quote = $this->cartProvider->getQuote($cartId);

        $response = CheckoutAPI::get()
            ->solicitation((string)$quote->getStore()->getId())
            ->solicitFor($this->createOrderRequestBuilderFactory->create([
                'cartId' => $quote->getId(),
                'storeId' => (string)$quote->getStore()->getId(),
            ]));

        if (!$response->isSuccessful()) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.serverError'));
        }

        $payload = [];
        if (!empty($this->request->getContent())) {
            $payload = $this->jsonSerializer->unserialize($this->request->getContent());
        }

        $product = !empty($payload['product_data']['ssequra_product']) ? $payload['product_data']['ssequra_product'] : null;
        $campaign = !empty($payload['product_data']['sequra_campaign']) ? $payload['product_data']['sequra_campaign'] : null;

        $formResponse = CheckoutAPI::get()
            ->solicitation((string)$quote->getStore()->getId())
            ->getIdentificationForm($quote->getId(), $product, $campaign);

        if (!$formResponse->isSuccessful()) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.serverError'));
        }

        return $formResponse->getIdentificationForm()->getForm();
    }
}
