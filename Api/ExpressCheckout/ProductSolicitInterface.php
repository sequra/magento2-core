<?php

namespace Sequra\Core\Api\ExpressCheckout;

/**
 * Interface ProductSolicitInterface
 *
 * Solicits a SeQura Express Checkout order for a single product on the product detail page.
 * A detached temporary quote is built from the posted add-to-cart form for the logged in
 * customer, leaving the shopper's real cart untouched, and the identification form HTML is
 * returned.
 */
interface ProductSolicitInterface
{
    /**
     * Solicits the Express Checkout order for the viewed product and returns the form HTML.
     *
     * @param mixed[] $payload Add-to-cart form data (product, qty, options).
     *
     * @return string
     */
    public function solicit(array $payload): string;
}
