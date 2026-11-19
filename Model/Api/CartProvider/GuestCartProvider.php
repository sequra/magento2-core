<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;

class GuestCartProvider implements CartProvider
{
    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;
    /**
     * @var CartRepositoryInterface
     * @phpstan-ignore-next-line
     */
    private $cartRepository;

    /**
     * GuestCartProvider constructor.
     *
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        GuestCartRepositoryInterface $guestCartRepository,
        CartRepositoryInterface $cartRepository
    ) {
        $this->guestCartRepository = $guestCartRepository;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Gets the quote for a guest cart
     *
     * @param string $cartId Guest cart ID
     *
     * @return Quote Quote instance
     * @throws NoSuchEntityException If cart is not found or not active
     */
    public function getQuote(string $cartId): Quote
    {
        /** @var Quote $quote */
        $quote = $this->guestCartRepository->get($cartId);
        if (!$quote->getIsActive()) {
            throw NoSuchEntityException::singleField('cartId', $cartId);
        }

        return $quote;
    }
}
