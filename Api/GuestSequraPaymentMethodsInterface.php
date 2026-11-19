<?php

namespace Sequra\Core\Api;

/**
 * Interface GuestSequraPaymentMethodsInterface
 *
 */
interface GuestSequraPaymentMethodsInterface
{
    /**
     * Fetches Sequra payment methods for guest customers
     *
     * @api
     * @param string $cartId
     * @param string $formKey
     * @return mixed[]
     */
    public function getAvailablePaymentMethods(string $cartId, string $formKey): array;

    /**
     * Fetches Sequra payment identification form for guest customers
     *
     * @param string $cartId
     * @param string $formKey
     * @return string
     */
    public function getForm(string $cartId, string $formKey): string;
}
