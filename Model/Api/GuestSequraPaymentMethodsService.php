<?php

namespace Sequra\Core\Model\Api;

use Sequra\Core\Api\GuestSequraPaymentMethodsInterface;

class GuestSequraPaymentMethodsService implements GuestSequraPaymentMethodsInterface
{
    /**
     * @var FormValidationSequraPaymentMethodsService
     */
    private $paymentMethodsService;

    /**
     * GuestSequraPaymentMethodsService constructor.
     * @param FormValidationSequraPaymentMethodsService $paymentMethodsService
     */
    public function __construct(FormValidationSequraPaymentMethodsService $paymentMethodsService)
    {
        $this->paymentMethodsService = $paymentMethodsService;
    }

    /**
     * Get available payment methods for a given cart ID and form key
     *
     * @param string $cartId Cart identifier
     * @param string $formKey Form key for CSRF protection
     *
     * @return array<array<string, string>> Array of available payment methods
     */
    public function getAvailablePaymentMethods(string $cartId, string $formKey): array
    {
        return $this->paymentMethodsService->getAvailablePaymentMethods($cartId, $formKey);
    }

    /**
     * Get the form for a given cart ID and form key
     *
     * @param string $cartId Cart identifier
     * @param string $formKey Form key for CSRF protection
     *
     * @return string The form HTML
     */
    public function getForm(string $cartId, string $formKey): string
    {
        return $this->paymentMethodsService->getForm($cartId, $formKey);
    }
}
