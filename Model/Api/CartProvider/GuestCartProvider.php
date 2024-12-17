<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Class GuestCartProvider
 *
 * @package Sequra\Core\Model\Api\CartProvider
 */
class GuestCartProvider implements CartProvider
{
    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    /**
     * GuestCartProvider constructor.
     *
     * @param GuestCartRepositoryInterface $guestCartRepository
     */
    public function __construct(
        GuestCartRepositoryInterface $guestCartRepository
    ) {
        $this->guestCartRepository = $guestCartRepository;
    }

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
