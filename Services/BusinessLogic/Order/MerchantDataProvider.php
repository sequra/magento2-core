<?php

namespace Sequra\Core\Services\BusinessLogic\Order;

use Magento\Framework\UrlInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Order\MerchantDataProviderInterface;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Options;

/**
 * Class MerchantDataProvider.
 *
 * @package Sequra\Core\Services\BusinessLogic\Order
 */
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
     * @return ?string
     */
    public function getApprovedCallback(): ?string
    {
        return null;
    }

    /**
     * @return ?string
     */
    public function getRejectedCallback(): ?string
    {
        return null;
    }

    /**
     * @return ?string
     */
    public function getPartPaymentDetailsGetter(): ?string
    {
        return null;
    }

    /**
     * @return ?string
     */
    public function getNotifyUrl(): ?string
    {
        return $this->urlBuilder->getUrl('sequra/webhook');
    }

    /**
     * @param string $cartId
     *
     * @return ?string
     */
    public function getReturnUrlForCartId(string $cartId): ?string
    {
      return $this->urlBuilder->getUrl('sequra/comeback', ['cartId' => $cartId]);
    }

    /**
     * @return ?string
     */
    public function getEditUrl(): ?string
    {
        return null;
    }

    /**
     * @return ?string
     */
    public function getAbortUrl(): ?string
    {
        return null;
    }

    /**
     * @return ?string
     */
    public function getApprovedUrl(): ?string
    {
        return null;
    }

    /**
     * @return ?string
     */
    public function getOptions(): ?Options
    {
        return null;
    }

    /**
     * @return string
     */
    public function getEventsWebhookUrl(): string
    {
        return $this->urlBuilder->getUrl('sequra/webhook');
    }

    /**
     * @return string[]
     */
    public function getNotificationParametersForCartId(string $cartId): array
    {
        return ['cartId' => $cartId];
    }

    /**
     * @return string[]
     */
    public function getEventsWebhookParametersForCartId(string $cartId): array
    {
        return ['cartId' => $cartId];
    }
}
