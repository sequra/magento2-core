<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Class CustomerCartProvider
 *
 * @package Sequra\Core\Model\Api\CartProvider
 */
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

    public function getQuote(string $cartId): Quote
    {
        /** @var Quote $quote */
        $quote = $this->quoteResotory->getActive($cartId);

        return $quote;
    }
}
