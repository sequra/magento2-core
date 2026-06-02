<?php

namespace Sequra\Core\Model\Api\ExpressCheckout;

use Magento\Framework\Exception\LocalizedException;
use Sequra\Core\Api\ExpressCheckout\SolicitInterface;

/**
 * Class SolicitService
 *
 * Express Checkout solicit endpoint for logged in customers.
 */
class SolicitService implements SolicitInterface
{
    /**
     * @var BaseSolicitService
     */
    private BaseSolicitService $solicitService;

    /**
     * SolicitService constructor.
     *
     * @param BaseSolicitService $solicitService
     */
    public function __construct(BaseSolicitService $solicitService)
    {
        $this->solicitService = $solicitService;
    }

    /**
     * Solicits the Express Checkout order and returns the identification form HTML.
     *
     * @param string $cartId Cart ID
     *
     * @return string Identification form HTML
     *
     * @throws LocalizedException If the order cannot be solicited
     */
    public function solicit(string $cartId): string
    {
        return $this->solicitService->solicit($cartId);
    }
}
