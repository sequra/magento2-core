<?php

namespace Sequra\Core\Api;

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
     * @param string $formKey
     * @return mixed[]
     */
    public function getAvailablePaymentMethods(string $cartId, string $formKey): array;

    /**
     * Fetches Sequra payment identification form for logged in customers
     *
     * @api
     * @param string $cartId
     * @param string $formKey
     * @return string
     */
    public function getForm(string $cartId, string $formKey): string;
}
