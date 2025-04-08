<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class CustomerCartProvider implements CartProvider
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteResotory;

    /**
     * CustomerCartProvider constructor.
     *
     * @param CartRepositoryInterface $quoteResotory
     */
    public function __construct(CartRepositoryInterface $quoteResotory)
    {
        $this->quoteResotory = $quoteResotory;
    }

    /**
     * Gets the quote for a customer cart
     *
     * @param string $cartId Customer cart ID
     *
     * @return Quote Quote instance
     */
    public function getQuote(string $cartId): Quote
    {
        /** @var Quote $quote */
        $quote = $this->quoteResotory->getActive($cartId);

        return $quote;
    }
}
