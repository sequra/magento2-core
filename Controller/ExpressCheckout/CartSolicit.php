<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
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
 * customer's current session cart and echoes the form HTML. Guests get HTTP 401, which the
 * storefront answers with the login pop-up before retrying.
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
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
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
     * @param CustomerSession $customerSession
     * @param SolicitInterface $solicitService
     * @param SolicitRateLimiter $rateLimiter
     */
    public function __construct(
        RawFactory $resultRawFactory,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        SolicitInterface $solicitService,
        SolicitRateLimiter $rateLimiter
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->solicitService = $solicitService;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Solicits the Express Checkout order for the current session cart.
     *
     * Returns the identification-form HTML as a raw text/html response. Solicit creates
     * per-customer state and the response is per-session, so it must never be page-cached.
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $result = $this->resultRawFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true)
            ->setHeader('Pragma', 'no-cache', true);

        try {
            if (!$this->customerSession->isLoggedIn()) {
                // 401 tells the storefront to open the login pop-up and retry (the same
                // contract as the product-page solicit endpoint).
                return $result->setHttpResponseCode(WebapiException::HTTP_UNAUTHORIZED)->setContents('');
            }

            // Throttle per customer: each solicit creates a SeQura order, so bound flooding.
            if ($this->rateLimiter->isExceeded((string)$this->customerSession->getCustomerId())) {
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
        } catch (WebapiException $e) {
            // 422 not eligible — surfaced to the library's onError callback; the body is
            // irrelevant to it.
            return $result->setHttpResponseCode($e->getHttpCode())->setContents('');
        } catch (Exception $e) {
            Logger::logError('Express Checkout cart solicit failed: ' . $e->getMessage());

            return $result->setHttpResponseCode(500)->setContents('');
        }
    }
}
