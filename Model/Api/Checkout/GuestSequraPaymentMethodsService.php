<?php

namespace Sequra\Core\Model\Api\Checkout;

use Sequra\Core\Api\Checkout\GuestSequraPaymentMethodsInterface;

class GuestSequraPaymentMethodsService implements GuestSequraPaymentMethodsInterface
{
    /**
     * @var BaseSequraPaymentMethodsService
     */
    private $paymentMethodsService;

    /**
     * GuestSequraPaymentMethodsService constructor.
     * @param BaseSequraPaymentMethodsService $paymentMethodsService
     */
    public function __construct(BaseSequraPaymentMethodsService $paymentMethodsService)
    {
        $this->paymentMethodsService = $paymentMethodsService;
    }

    /**
     * Returns available payment methods for the given guest cart
     *
     * @param string $cartId Guest cart ID
     *
     * @return array<array<string, string>> Available payment methods
     */
    public function getAvailablePaymentMethods(string $cartId): array
    {
        return $this->paymentMethodsService->getAvailablePaymentMethods($cartId);
    }

    /**
     * Gets the payment form for the given guest cart
     *
     * @param string $cartId Guest cart ID
     *
     * @return string Payment form HTML
     */
    public function getForm(string $cartId): string
    {
        return $this->paymentMethodsService->getForm($cartId);
    }
}
