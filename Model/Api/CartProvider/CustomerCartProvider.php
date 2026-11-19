<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class CustomerCartProvider implements CartProvider
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * CustomerCartProvider constructor.
     *
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(CartRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
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
        $quote = $this->quoteRepository->getActive((int) $cartId);

        return $quote;
    }
}
