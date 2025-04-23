<?php

namespace Sequra\Core\Model\Api;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Sequra\Core\Model\Api\Checkout\BaseSequraPaymentMethodsService;

/**
 * Class SequraPaymentMethodsService
 *
 */
class FormValidationSequraPaymentMethodsService
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
     * @var BaseSequraPaymentMethodsService
     */
    private $paymentMethodsService;

    /**
     * AbstractInternalApiController constructor.
     *
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param BaseSequraPaymentMethodsService $paymentMethodsService
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        BaseSequraPaymentMethodsService $paymentMethodsService
    ) {
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->paymentMethodsService = $paymentMethodsService;
    }

    /**
     * Get available payment methods for a given cart ID and form key
     *
     * @param string $cartId Cart identifier
     * @param string $formKey Form key for CSRF protection
     *
     * @return array<array<string, string>> Available payment methods
     */
    public function getAvailablePaymentMethods(string $cartId, string $formKey): array
    {
        $this->validateRequest($formKey);

        return $this->paymentMethodsService->getAvailablePaymentMethods($cartId);
    }

    /**
     * Get the form for a given cart ID and form key
     *
     * @param string $cartId Cart identifier
     * @param string $formKey Form key for CSRF protection
     */
    public function getForm(string $cartId, string $formKey): string
    {
        $this->validateRequest($formKey);

        return $this->paymentMethodsService->getForm($cartId);
    }

    /**
     * Validate the request
     *
     * @param string $formKey
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function validateRequest($formKey): bool
    {
        $isAjax = $this->request->isAjax();
        // Post value has to be manually set since it will have no post data when this function is accessed
        $formKeyValid = $this->formKeyValidator->validate($this->request->setPostValue('form_key', $formKey));

        if (!$isAjax || !$formKeyValid) {
            // TODO: Direct throw of generic Exception is discouraged. Use context specific instead
            // phpcs:ignore Magento2.Exceptions.DirectThrow.FoundDirectThrow
            throw new \Exception('Invalid request', 401);
        }

        return true;
    }
}
