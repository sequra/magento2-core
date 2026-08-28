<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use SeQura\Core\Infrastructure\Logger\Logger;
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
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true)
            ->setHeader('Pragma', 'no-cache', true);

        try {
            $rawQuoteId = $this->checkoutSession->getQuote()->getId();
            $quoteId = is_scalar($rawQuoteId) ? (string)$rawQuoteId : '';
            if ($quoteId === '') {
                return $result->setHttpResponseCode(WebapiException::HTTP_BAD_REQUEST)->setContents('');
            }

            // Throttle per cart: each solicit creates a SeQura order, so bound flooding. The cart
            // id is the key rather than the customer id, which is empty for a guest and would put
            // every guest in one shared bucket. Resolved above, so it is never empty here.
            if ($this->rateLimiter->isExceeded($quoteId)) {
                return $result->setHttpResponseCode(429)->setContents('');
            }

            return $result
                ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
                ->setContents($this->solicitService->solicit($quoteId));
        } catch (WebapiException $e) {
            // 422 not eligible — surfaced to the library's onError callback; the body is
            // irrelevant to it.
            return $result->setHttpResponseCode($e->getHttpCode())->setContents('');
        } catch (NoSuchEntityException $e) {
            // The session quote is gone/inactive (e.g. the order was placed in another tab),
            // so there is nothing to solicit — a 400, not an opaque 500.
            return $result->setHttpResponseCode(WebapiException::HTTP_BAD_REQUEST)->setContents('');
        } catch (Exception $e) {
            Logger::logError('Express Checkout cart solicit failed: ' . $e->getMessage());

            return $result->setHttpResponseCode(500)->setContents('');
        }
    }
}
