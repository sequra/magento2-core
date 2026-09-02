<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Webapi\Exception as WebapiException;
use Sequra\Core\Api\ExpressCheckout\SolicitInterface;
use Sequra\Core\Model\ExpressCheckout\SolicitRateLimiter;

/**
 * Class CartSolicit
 *
 * Cart/mini-cart Express Checkout solicit endpoint for the SeQura button rendered by the shared
 * CDN library. The library calls the element's data-url with a plain GET and expects the
 * identification-form HTML back as a raw text/html body, so this controller solicits the
 * shopper's current session cart and echoes the form HTML.
 *
 * No login is required: express runs on whatever the session cart already holds, and the
 * express screen collects the address and email the merchant could not supply.
 */
class CartSolicit implements HttpGetActionInterface
{
    use ResponseTrait;

    /**
     * @var RawFactory
     */
    private RawFactory $resultRawFactory;
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;
    /**
     * @var SolicitInterface
     */
    private SolicitInterface $solicitService;
    /**
     * @var SolicitRateLimiter
     */
    private SolicitRateLimiter $rateLimiter;

    /**
     * CartSolicit constructor.
     *
     * @param RawFactory $resultRawFactory
     * @param CheckoutSession $checkoutSession
     * @param SolicitInterface $solicitService
     * @param SolicitRateLimiter $rateLimiter
     */
    public function __construct(
        RawFactory $resultRawFactory,
        CheckoutSession $checkoutSession,
        SolicitInterface $solicitService,
        SolicitRateLimiter $rateLimiter
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->checkoutSession = $checkoutSession;
        $this->solicitService = $solicitService;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Solicits the Express Checkout order for the current session cart.
     *
     * Returns the identification-form HTML as a raw text/html response. Solicit creates
     * per-shopper state and the response is per-session, so it must never be page-cached.
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $result = $this->resultRawFactory->create();
        $this->noStore($result);

        try {
            // Throttle: each solicit creates a SeQura order, so bound flooding. The session
            // identifies the shopper — the customer id is empty for a guest, and a cart id would
            // let a shopper reset their own throttle by rebuilding the cart. Never empty: reading
            // it starts the session if it has not started already. It is not the whole key though:
            // no login is required, so the limiter also counts the caller's address, which a
            // cookie-less caller cannot renew the way it renews a session.
            if ($this->rateLimiter->isExceeded((string)$this->checkoutSession->getSessionId())) {
                return $result->setHttpResponseCode(429)->setContents('');
            }

            $rawQuoteId = $this->checkoutSession->getQuote()->getId();
            $quoteId = is_scalar($rawQuoteId) ? (string)$rawQuoteId : '';
            if ($quoteId === '') {
                return $result->setHttpResponseCode(WebapiException::HTTP_BAD_REQUEST)->setContents('');
            }

            return $result
                ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
                ->setContents($this->solicitService->solicit($quoteId));
        } catch (Exception $e) {
            return $result
                ->setHttpResponseCode($this->failureCode($e, 'Express Checkout cart solicit'))
                ->setContents('');
        }
    }
}
