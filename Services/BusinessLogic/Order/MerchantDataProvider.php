<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\UrlInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\MerchantDataProviderInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Options;
use Sequra\Core\Model\ExpressCheckout\ExpressCheckoutFlow;

class MerchantDataProvider implements MerchantDataProviderInterface
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;
    /**
     * @var ExpressCheckoutFlow
     */
    private ExpressCheckoutFlow $expressFlow;

    /**
     * @param UrlInterface $urlBuilder
     * @param ExpressCheckoutFlow $expressFlow
     */
    public function __construct(UrlInterface $urlBuilder, ExpressCheckoutFlow $expressFlow)
    {
        $this->urlBuilder = $urlBuilder;
        $this->expressFlow = $expressFlow;
    }

    /**
     * Returns approved callback
     *
     * @return ?string
     */
    public function getApprovedCallback(): ?string
    {
        return null;
    }

    /**
     * Returns rejected callback
     *
     * @return ?string
     */
    public function getRejectedCallback(): ?string
    {
        return null;
    }

    /**
     * Returns part payment details
     *
     * @return ?string
     */
    public function getPartPaymentDetailsGetter(): ?string
    {
        return null;
    }

    /**
     * Returns notify url
     *
     * @return ?string
     */
    public function getNotifyUrl(): ?string
    {
        return $this->urlBuilder->getUrl('sequra/webhook');
    }

    /**
     * Returns return url for given cart id
     *
     * @param string $cartId
     *
     * @return ?string
     */
    public function getReturnUrlForCartId(string $cartId): ?string
    {
        return $this->urlBuilder->getUrl('sequra/comeback', ['cartId' => $cartId]);
    }

    /**
     * Returns edit url
     *
     * @return ?string
     */
    public function getEditUrl(): ?string
    {
        return null;
    }

    /**
     * Returns abort url
     *
     * @return ?string
     */
    public function getAbortUrl(): ?string
    {
        return null;
    }

    /**
     * Returns approved url
     *
     * @return ?string
     */
    public function getApprovedUrl(): ?string
    {
        return null;
    }

    /**
     * Returns options
     *
     * Declares `addresses_may_be_missing`, but only for Express Checkout: it solicits before the
     * shopper has an address (that is what the express screen collects), so that flow cannot
     * promise both addresses on its create-order request.
     *
     * Regular checkout deliberately keeps sending nothing. This hook is shared by every flow and
     * has no way of its own to tell them apart ({@see ExpressCheckoutFlow} is what supplies that),
     * and declaring the flag unconditionally would drop SeQura's server-side refusal of a regular
     * checkout order whose address block arrived broken or empty — a third-party address plugin
     * misbehaving, a mis-mapped B2B address. That refusal is how such a bug surfaces at checkout
     * instead of becoming an order nobody can ship.
     *
     * @return Options|null
     */
    public function getOptions(): ?Options
    {
        return $this->expressFlow->isSoliciting() ? new Options(null, null, true) : null;
    }

    /**
     * Returns events webhook url
     *
     * @return string
     */
    public function getEventsWebhookUrl(): string
    {
        return $this->urlBuilder->getUrl('sequra/webhook');
    }

    /**
     * Returns notifications parameters for cart id
     *
     * @param string $cartId
     *
     * @return string[]
     */
    public function getNotificationParametersForCartId(string $cartId): array
    {
        return ['cartId' => $cartId];
    }

    /**
     * Returns events webhook parameters for cart id
     *
     * @param string $cartId
     *
     * @return string[]
     */
    public function getEventsWebhookParametersForCartId(string $cartId): array
    {
        return ['cartId' => $cartId];
    }
}
