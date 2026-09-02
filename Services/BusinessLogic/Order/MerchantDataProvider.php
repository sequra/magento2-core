<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\UrlInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\MerchantDataProviderInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Options;

class MerchantDataProvider implements MerchantDataProviderInterface
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param UrlInterface $urlBuilder
     */
    public function __construct(UrlInterface $urlBuilder)
    {
        $this->urlBuilder = $urlBuilder;
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
     * Declares `addresses_may_be_missing`: Express Checkout solicits before the shopper has an
     * address (that is what the express screen collects), so this integration cannot promise
     * both addresses on every create-order request. Regular checkout keeps sending them — the
     * flag only stops SeQura rejecting the ones that cannot.
     *
     * @return Options|null
     */
    public function getOptions(): ?Options
    {
        return new Options(null, null, true);
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
