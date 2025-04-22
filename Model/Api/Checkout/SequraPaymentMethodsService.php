<?php

namespace Sequra\Core\Model\Api\Checkout;

use Sequra\Core\Api\Checkout\SequraPaymentMethodsInterface;

class SequraPaymentMethodsService implements SequraPaymentMethodsInterface
{
    /**
     * @var BaseSequraPaymentMethodsService
     */
    private $paymentMethodsService;

    /**
     * SequraPaymentMethodsService constructor.
     * @param BaseSequraPaymentMethodsService $paymentMethodsService
     */
    public function __construct(BaseSequraPaymentMethodsService $paymentMethodsService)
    {
        $this->paymentMethodsService = $paymentMethodsService;
    }

    /**
     * Returns available payment methods for the given cart
     *
     * @param string $cartId Cart ID
     *
     * @return array<array<string, string>> Available payment methods
     */
    public function getAvailablePaymentMethods(string $cartId): array
    {
        return $this->paymentMethodsService->getAvailablePaymentMethods($cartId);
    }

    /**
     * Gets the payment form for the given cart
     *
     * @param string $cartId Cart ID
     *
     * @return string Payment form HTML
     */
    public function getForm(string $cartId): string
    {
        return $this->paymentMethodsService->getForm($cartId);
    }
}
