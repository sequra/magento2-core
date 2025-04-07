<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Quote\Model\Quote;

interface CartProvider
{
    public function getQuote(string $cartId): Quote;
}
