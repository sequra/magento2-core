<?php

namespace Sequra\Core\Model\Api\ExpressCheckout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Sequra\Core\Api\ExpressCheckout\ProductSolicitInterface;
use Sequra\Core\Model\ExpressCheckout\TemporaryCartBuilder;

/**
 * Class ProductSolicitService
 *
 * Express Checkout solicit endpoint for the product detail page. Builds a detached temporary
 * quote from the posted add-to-cart form, then delegates to the shared solicit flow which
 * resolves shipping and solicits the identification form. The temporary cart ID is never
 * exposed to the client.
 */
class ProductSolicitService implements ProductSolicitInterface
{
    /**
     * Buy-request keys forwarded from the posted form to Magento's add-to-cart processor.
     */
    private const BUY_REQUEST_KEYS = [
        'super_attribute',
        'bundle_option',
        'bundle_option_qty',
        'super_group',
        'options',
        'selected_configurable_option',
    ];

    /**
     * @var TemporaryCartBuilder
     */
    private TemporaryCartBuilder $temporaryCartBuilder;
    /**
     * @var BaseSolicitService
     */
    private BaseSolicitService $solicitService;

    /**
     * ProductSolicitService constructor.
     *
     * @param TemporaryCartBuilder $temporaryCartBuilder
     * @param BaseSolicitService $solicitService
     */
    public function __construct(TemporaryCartBuilder $temporaryCartBuilder, BaseSolicitService $solicitService)
    {
        $this->temporaryCartBuilder = $temporaryCartBuilder;
        $this->solicitService = $solicitService;
    }

    /**
     * Solicits the Express Checkout order for the viewed product and returns the form HTML.
     *
     * @param mixed[] $payload Add-to-cart form data (product, qty, options).
     *
     * @return string
     *
     * @throws WebapiException On invalid request (400), guest caller (401) or not eligible (422).
     * @throws LocalizedException If the order cannot be solicited.
     */
    public function solicit(array $payload): string
    {
        $productId = (isset($payload['product']) && is_scalar($payload['product'])) ? (string)$payload['product'] : '';
        if ($productId === '') {
            throw new WebapiException(__('Invalid Express Checkout request.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        $qty = (isset($payload['qty']) && is_scalar($payload['qty'])) ? (float)$payload['qty'] : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }

        $buyRequest = ['qty' => $qty];
        foreach (self::BUY_REQUEST_KEYS as $key) {
            if (isset($payload[$key])) {
                $buyRequest[$key] = $payload[$key];
            }
        }

        try {
            $cartId = $this->temporaryCartBuilder->build($productId, $buyRequest);
        } catch (NoSuchEntityException $e) {
            // Unknown product id in the request — a bad/forged request, not a server fault.
            throw new WebapiException(__('Invalid Express Checkout request.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        return $this->solicitService->solicit((string)$cartId);
    }
}
