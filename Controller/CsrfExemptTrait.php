<?php

namespace Sequra\Core\Controller;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Trait CsrfExemptTrait
 *
 * Opts a controller out of Magento's form-key validation, for the endpoints that genuinely cannot
 * carry a form key: a request that never came from a page of ours has no session of ours to have
 * minted one.
 *
 * It replaced a plugin that exempted the whole `sequra` frontend route, which also exempted the
 * state-changing endpoints the shopper's own browser calls. Exempting per action keeps that
 * decision next to the code it applies to, and each class says in its own docblock why it
 * qualifies — the reason differs (a signature, a guid, a return leg from SeQura's domain) even
 * though the code does not.
 *
 * Not for an endpoint that merely wants a different rejection: implement
 * {@see \Magento\Framework\App\CsrfAwareActionInterface} directly and return null from
 * validateForCsrf() so Magento still decides, as
 * {@see \Sequra\Core\Controller\ExpressCheckout\CartUpdate} does.
 */
trait CsrfExemptTrait
{
    /**
     * No exception to shape: the request is never rejected for a missing form key.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Accepts the request without a form key.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
