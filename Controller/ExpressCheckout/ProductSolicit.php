<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Sequra\Core\Api\ExpressCheckout\ProductSolicitInterface;
use Sequra\Core\Model\ExpressCheckout\SolicitRateLimiter;

/**
 * Class ProductSolicit
 *
 * Product-page Express Checkout solicit endpoint for the SeQura button rendered by the shared
 * CDN library. The library calls the element's data-url with a plain GET and expects the
 * identification-form HTML back as a raw text/html body (a JSON-encoded WebAPI response does
 * not satisfy that contract), so this controller re-serializes the query string into the
 * payload the existing solicit service already understands and echoes the form HTML.
 *
 * No login is required: the temporary quote is built for whoever is browsing, guest or customer,
 * and the express screen collects the address and email the merchant could not supply.
 */
class ProductSolicit implements HttpGetActionInterface
{
    use ResponseTrait;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;
    /**
     * @var RawFactory
     */
    private RawFactory $resultRawFactory;
    /**
     * @var ProductSolicitInterface
     */
    private ProductSolicitInterface $solicitService;
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var SolicitRateLimiter
     */
    private SolicitRateLimiter $rateLimiter;

    /**
     * ProductSolicit constructor.
     *
     * @param HttpRequest $request
     * @param RawFactory $resultRawFactory
     * @param ProductSolicitInterface $solicitService
     * @param CustomerSession $customerSession
     * @param SolicitRateLimiter $rateLimiter
     */
    public function __construct(
        HttpRequest $request,
        RawFactory $resultRawFactory,
        ProductSolicitInterface $solicitService,
        CustomerSession $customerSession,
        SolicitRateLimiter $rateLimiter
    ) {
        $this->request = $request;
        $this->resultRawFactory = $resultRawFactory;
        $this->solicitService = $solicitService;
        $this->customerSession = $customerSession;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Solicits the Express Checkout order for the query-string add-to-cart data.
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
            // Throttle on the same key as the other two solicit surfaces: session plus caller
            // address. Each solicit builds a temporary quote and creates a SeQura order, so bound
            // flooding. This surface needs no session state at all, so the address half of the key
            // is what stops a cookie-less caller minting orders without limit.
            if ($this->rateLimiter->isExceeded((string)$this->customerSession->getSessionId())) {
                return $result->setHttpResponseCode(429)->setContents('');
            }

            return $result
                ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
                ->setContents($this->solicitService->solicit($this->request->getParams()));
        } catch (Exception $e) {
            return $result
                ->setHttpResponseCode($this->failureCode($e, 'Express Checkout product solicit'))
                ->setContents('');
        }
    }
}
