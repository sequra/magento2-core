<?php

namespace Sequra\Core\DataAccess\Entities;

use SeQura\Core\Infrastructure\ORM\Configuration\EntityConfiguration;
use SeQura\Core\Infrastructure\ORM\Configuration\IndexMap;
use SeQura\Core\Infrastructure\ORM\Entity;

/**
 * Class PaymentMethods
 *
 * @package Sequra\Core\DataAccess\Entities
 */
class PaymentMethods extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    public const CLASS_NAME = __CLASS__;
    /**
     * @var string
     */
    protected $storeId;
    /**
     * @var string
     */
    protected $merchantId;
    /**
     * @var PaymentMethod[]
     */
    protected $paymentMethods;

    /**
     * @inheritDoc
     */
    public function getConfig(): EntityConfiguration
    {
        $indexMap = new IndexMap();

        $indexMap->addStringIndex('storeId');
        $indexMap->addStringIndex('merchantId');

        return new EntityConfiguration($indexMap, 'PaymentMethods');
    }

    /**
     * @inheritDoc
     *
     * @throws \Exception
     */
    public function inflate(array $data): void
    {
        parent::inflate($data);

        $this->storeId = $data['storeId'] ?? '';
        $this->merchantId = $data['merchantId'] ?? '';
        $paymentMethods = $data['paymentMethods'] ?? [];

        foreach ($paymentMethods as $paymentMethod) {
            $this->paymentMethods[] = PaymentMethod::fromArray($paymentMethod);
        }
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        $data['storeId'] = $this->storeId;
        $data['merchantId'] = $this->merchantId;
        $data['paymentMethods'] = [];

        foreach ($this->paymentMethods as $paymentMethod) {
            $data['paymentMethods'][] = [
                'product' => $paymentMethod->getProduct(),
                'title' => $paymentMethod->getTitle() ?: '',
                'longTitle' => $paymentMethod->getLongTitle() ?: '',
                'startsAt' => $paymentMethod->getStartsAt() ?: '',
                'endsAt' => $paymentMethod->getEndsAt() ?: '',
                'campaign' => $paymentMethod->getCampaign() ?: '',
                'claim' => $paymentMethod->getClaim() ?: '',
                'description' => $paymentMethod->getDescription() ?: '',
                'icon' => $paymentMethod->getIcon() ?: '',
                'costDescription' => $paymentMethod->getCostDescription() ?: '',
                'minAmount' => $paymentMethod->getMinAmount() ?: null,
                'maxAmount' => $paymentMethod->getMaxAmount() ?: null
            ];
        }

        return $data;
    }

    public function getStoreId(): string
    {
        return $this->storeId;
    }

    public function setStoreId(string $storeId): void
    {
        $this->storeId = $storeId;
    }

    /**
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    /**
     * @param string $merchantId
     * @return void
     */
    public function setMerchantId(string $merchantId): void
    {
        $this->merchantId = $merchantId;
    }

    /**
     * @return PaymentMethod[]
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @param PaymentMethod[] $paymentMethods
     */
    public function setPaymentMethods(array $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
