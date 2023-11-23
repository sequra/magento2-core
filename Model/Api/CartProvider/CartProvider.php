<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Quote\Model\Quote;

/**
 * Interface CartProvider
 *
 * @package Sequra\Core\Model\Api\CartProvider
 */
interface CartProvider
{
    public function getQuote(string $cartId): Quote;
}
