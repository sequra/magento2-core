<?php

namespace Sequra\Core\Api\Checkout;

/**
 * Interface SequraPaymentMethodsInterface
 *
 */
interface SequraPaymentMethodsInterface
{
    /**
     * Fetches Sequra payment methods for logged in customers
     *
     * @param string $cartId
     * @return mixed[]
     */
    public function getAvailablePaymentMethods(string $cartId): array;

    /**
     * Fetches Sequra payment identification form for logged in customers
     *
     * @param string $cartId
     * @return string
     */
    public function getForm(string $cartId): string;
}
