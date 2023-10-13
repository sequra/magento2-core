<?php

namespace Sequra\Core\Api\Checkout;

/**
 * Interface GuestSequraPaymentMethodsInterface
 *
 * @package Sequra\Core\Api\Checkout
 */
interface GuestSequraPaymentMethodsInterface
{
    /**
     * Fetches Sequra payment methods for guest customers
     *
     * @api
     * @param string $cartId
     * @return mixed[]
     */
    public function getAvailablePaymentMethods(string $cartId): array;

    /**
     * Fetches Sequra payment identification form for guest customers
     *
     * @api
     * @param string $cartId
     * @return string
     */
    public function getForm(string $cartId): string;
}
