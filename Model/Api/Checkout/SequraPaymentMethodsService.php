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

    public function getAvailablePaymentMethods(string $cartId): array
    {
        return $this->paymentMethodsService->getAvailablePaymentMethods($cartId);
    }

    public function getForm(string $cartId): string
    {
        return $this->paymentMethodsService->getForm($cartId);
    }
}
