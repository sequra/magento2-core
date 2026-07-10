<?php

namespace Sequra\Core\Api\ExpressCheckout;

/**
 * Interface SolicitInterface
 *
 * Solicits a SeQura Express Checkout order for a logged in customer's cart and
 * returns the identification form HTML.
 */
interface SolicitInterface
{
    /**
     * Solicits the Express Checkout order and returns the identification form HTML.
     *
     * @param string $cartId
     *
     * @return string
     */
    public function solicit(string $cartId): string;
}
