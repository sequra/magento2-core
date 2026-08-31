<?php

namespace Sequra\Core\Model\Api\ExpressCheckout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Sequra\Core\Api\ExpressCheckout\SolicitInterface;
use Sequra\Core\Model\ExpressCheckout\TemporaryCartBuilder;

/**
 * Class SolicitService
 *
 * Express Checkout solicit endpoint for the cart page and mini-cart. The
 * solicit runs against a detached clone of the shopper's cart, never the live checkout-session
 * quote: resolving shipping imports the default address, forces the SeQura payment method and adds
 * a shipping line, so mutating the real quote would leave the cart altered if the shopper cancels
 * the SeQura form. The clone is placed on success (its id is the SeQura order cart reference); the
 * real cart is emptied on the matching comeback return.
 */
class SolicitService implements SolicitInterface
{
    /**
     * Checkout-session key pairing the placed clone id with the live cart to empty once that clone
     * is completed. Read at comeback (Controller\Comeback), which matches the clone id so an
     * unrelated comeback (PDP express / regular checkout) never empties the wrong cart.
     */
    public const SESSION_KEY_CLONE = 'sequra_express_clone_id';

    /**
     * Checkout-session key holding the live cart id to empty on the matching comeback.
     */
    public const SESSION_KEY_SOURCE = 'sequra_express_source_cart_id';

    /**
     * @var BaseSolicitService
     */
    private BaseSolicitService $solicitService;
    /**
     * @var TemporaryCartBuilder
     */
    private TemporaryCartBuilder $temporaryCartBuilder;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * SolicitService constructor.
     *
     * @param BaseSolicitService $solicitService
     * @param TemporaryCartBuilder $temporaryCartBuilder
     * @param CartRepositoryInterface $quoteRepository
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        BaseSolicitService $solicitService,
        TemporaryCartBuilder $temporaryCartBuilder,
        CartRepositoryInterface $quoteRepository,
        CheckoutSession $checkoutSession
    ) {
        $this->solicitService = $solicitService;
        $this->temporaryCartBuilder = $temporaryCartBuilder;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Solicits the Express Checkout order for the shopper's cart and returns the form HTML.
     *
     * @param string $cartId Live cart ID (the checkout-session quote).
     *
     * @return string Identification form HTML
     *
     * @throws WebapiException If the customer is not eligible for Express Checkout (HTTP 422).
     * @throws NoSuchEntityException If the cart does not exist or is inactive.
     * @throws LocalizedException If the order cannot be solicited.
     */
    public function solicit(string $cartId): string
    {
        /** @var Quote $source */
        $source = $this->quoteRepository->getActive((int)$cartId);

        $cloneId = $this->temporaryCartBuilder->buildFromQuote($source);
        try {
            $form = $this->solicitService->solicit((string)$cloneId);

            // Record which live cart to empty when this specific clone completes. Keyed by the
            // clone id so the comeback for a different order (PDP express / regular checkout) does
            // not consume it and empty the wrong cart.
            // @phpstan-ignore-next-line magic method forwarded to Storage via SessionManager::__call
            $this->checkoutSession->setData(self::SESSION_KEY_CLONE, $cloneId);
            // @phpstan-ignore-next-line magic method forwarded to Storage via SessionManager::__call
            $this->checkoutSession->setData(self::SESSION_KEY_SOURCE, (int)$cartId);

            return $form;
        } finally {
            // Keep the clone out of active-cart resolution between solicits; it is reactivated at
            // order placement (OrderCreation::createOrder).
            $this->temporaryCartBuilder->deactivate($cloneId);
        }
    }
}
