<?php

namespace Sequra\Core\Api;

/**
 * Interface SequraPaymentMethodsInterface
 *
 * @package Sequra\Core\Api
 */
interface SequraPaymentMethodsInterface
{
    /**
     * Fetches Sequra payment methods for guest customers
     *
     * @param string $cartId
     * @param string $formKey
     * @return array
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
