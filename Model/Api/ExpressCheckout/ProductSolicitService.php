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
     * @param string $payload URL-encoded add-to-cart form data (product, qty, options).
     *
     * @return string
     *
     * @throws WebapiException If the request is invalid (HTTP 400), the caller is a guest (HTTP 401) or not eligible (HTTP 422).
     * @throws LocalizedException If the order cannot be solicited.
     */
    public function solicit(string $payload): string
    {
        $data = [];
        parse_str($payload, $data);

        $productId = (isset($data['product']) && is_scalar($data['product'])) ? (string)$data['product'] : '';
        if ($productId === '') {
            throw new WebapiException(__('Invalid Express Checkout request.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        $qty = (isset($data['qty']) && is_scalar($data['qty'])) ? (float)$data['qty'] : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }

        $buyRequest = ['qty' => $qty];
        foreach (self::BUY_REQUEST_KEYS as $key) {
            if (isset($data[$key])) {
                $buyRequest[$key] = $data[$key];
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
