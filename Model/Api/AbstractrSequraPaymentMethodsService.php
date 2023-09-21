<?php

namespace Sequra\Core\Model\Api;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote as QuoteEntity;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;

/**
 * Class SequraPaymentMethodsService
 *
 * @package Sequra\Core\Model\Api
 */
abstract class AbstractrSequraPaymentMethodsService
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var Validator
     */
    protected $formKeyValidator;
    /**
     * @var \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory
     */
    private $createOrderRequestBuilderFactory;
    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * AbstractInternalApiController constructor.
     * @param Http $request
     * @param Json $jsonSerializer
     * @param Validator $formKeyValidator
     * @param CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     */
    public function __construct(
        Http $request,
        Json $jsonSerializer,
        Validator $formKeyValidator,
        \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
    ) {
        $this->request = $request;
        $this->jsonSerializer = $jsonSerializer;
        $this->formKeyValidator = $formKeyValidator;
        $this->createOrderRequestBuilderFactory = $createOrderRequestBuilderFactory;
    }

    public function getAvailablePaymentMethods(string $cartId, string $formKey): array
    {
        $this->validateRequest($formKey);

        $quote = $this->getQuote($cartId);

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

    public function getForm(string $cartId, string $formKey): string
    {
        $this->validateRequest($formKey);

        $quote = $this->getQuote($cartId);

        $response = CheckoutAPI::get()
            ->solicitation((string)$quote->getStore()->getId())
            ->solicitFor($this->createOrderRequestBuilderFactory->create([
                'cartId' => $quote->getId(),
                'storeId' => (string)$quote->getStore()->getId(),
            ]));

        if (!$response->isSuccessful()) {
            throw new LocalizedException(__('An error occurred on the server. Please try to place the order again.'));
        }

        $payload = $this->jsonSerializer->unserialize($this->request->getContent());
        $product = !empty($payload['product_data']['ssequra_product']) ? $payload['product_data']['ssequra_product'] : null;
        $campaign = !empty($payload['product_data']['sequra_campaign']) ? $payload['product_data']['sequra_campaign'] : null;

        $formResponse = CheckoutAPI::get()
            ->solicitation((string)$quote->getStore()->getId())
            ->getIdentificationForm($quote->getId(), $product, $campaign);

        if (!$formResponse->isSuccessful()) {
            throw new LocalizedException(__('An error occurred on the server. Please try to place the order again.'));
        }

        return $formResponse->getIdentificationForm()->getForm();
    }

    /**
     * @param $formKey
     * @return bool
     * @throws \Exception
     */
    public function validateRequest($formKey): bool
    {
        $isAjax = $this->request->isAjax();
        // Post value has to be manually set since it will have no post data when this function is accessed
        $formKeyValid = $this->formKeyValidator->validate($this->request->setPostValue('form_key', $formKey));

        if (!$isAjax || !$formKeyValid) {
            throw new \Exception(
                'Invalid request',
                401
            );
        }

        return true;
    }

    abstract protected function getQuote(string $cartId): QuoteEntity;
}
