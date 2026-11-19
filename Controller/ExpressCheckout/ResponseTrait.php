<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Framework\Controller\AbstractResult;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use SeQura\Core\Infrastructure\Logger\Logger;

/**
 * Trait ResponseTrait
 *
 * The response boilerplate every Express Checkout storefront controller repeats: the no-store
 * headers and the failure-to-status mapping. Kept as a trait rather than a base class because the
 * controllers differ in the part that matters — Raw HTML for the solicit endpoints, JSON for the
 * others — so there is no shared execute() to inherit.
 */
trait ResponseTrait
{
    /**
     * Marks a response uncacheable.
     *
     * Every one of these endpoints answers per session and most of them create SeQura order
     * state, so none may ever be page-cached.
     *
     * @param AbstractResult $result
     *
     * @return void
     */
    private function noStore(AbstractResult $result): void
    {
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true)
            ->setHeader('Pragma', 'no-cache', true);
    }

    /**
     * The HTTP status to answer a failed request with.
     *
     * Logs only the failures that are genuinely the server's fault.
     *
     * @param Exception $e
     * @param string $context Log prefix naming the endpoint, e.g. 'Express Checkout cart solicit'.
     *
     * @return int
     */
    private function failureCode(Exception $e, string $context): int
    {
        if ($e instanceof WebapiException) {
            // 400 malformed request / unavailable carrier, 422 not eligible. Both are expected
            // outcomes the storefront handles, so neither is logged.
            return $e->getHttpCode();
        }

        if ($e instanceof NoSuchEntityException) {
            // The quote the request needs is gone or was never there (e.g. the order was placed in
            // another tab), so there is nothing to act on — a 400, not an opaque 500.
            return WebapiException::HTTP_BAD_REQUEST;
        }

        Logger::logError($context . ' failed: ' . $e->getMessage());

        return 500;
    }
}
