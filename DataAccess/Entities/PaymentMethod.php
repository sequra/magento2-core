<?php

namespace Sequra\Core\DataAccess\Entities;

use SeQura\Core\Infrastructure\ORM\Configuration\EntityConfiguration;
use SeQura\Core\Infrastructure\ORM\Configuration\IndexMap;
use SeQura\Core\Infrastructure\ORM\Entity;

/**
 * Class PaymentMethod
 *
 * @package Sequra\Core\DataAccess\Entities
 */
class PaymentMethod extends Entity
{

    /**
     * Fully qualified name of this class.
     */
    public const CLASS_NAME = __CLASS__;

    /**
     * @var string
     */
    protected $product;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var string
     */
    protected $longTitle;
    /**
     * @var string
     */
    protected $startsAt;
    /**
     * @var string
     */
    protected $endsAt;
    /**
     * @var string
     */
    protected $campaign;
    /**
     * @var string
     */
    protected $claim;
    /**
     * @var string
     */
    protected $description;
    /**
     * @var string
     */
    protected $icon;
    /**
     * @var string
     */
    protected $costDescription;
    /**
     * @var int
     */
    protected $minAmount;
    /**
     * @var int
     */
    protected $maxAmount;
    /**
     * @inheritDoc
     */
    public function getConfig(): EntityConfiguration
    {
        $indexMap = new IndexMap();

        $indexMap->addStringIndex('product');

        return new EntityConfiguration($indexMap, 'PaymentMethod');
    }

    /**
     * @inheritDoc
     */
    public function inflate(array $data): void
    {
        parent::inflate($data);

        $this->product = $data['product'];
        $this->title = $data['title'];
        $this->longTitle = $data['longTitle'];
        $this->startsAt = $data['startsAt'];
        $this->endsAt = $data['endsAt'];
        $this->campaign = $data['campaign'];
        $this->claim = $data['claim'];
        $this->description = $data['description'];
        $this->icon = $data['icon'];
        $this->costDescription = $data['costDescription'];
        $this->minAmount = $data['minAmount'];
        $this->maxAmount = $data['maxAmount'];
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        $data['product'] = $this->product;
        $data['title'] = $this->title;
        $data['longTitle'] = $this->longTitle;
        $data['startsAt'] = $this->startsAt;
        $data['endsAt'] = $this->endsAt;
        $data['campaign'] = $this->campaign;
        $data['claim'] = $this->claim;
        $data['description'] = $this->description;
        $data['icon'] = $this->icon;
        $data['costDescription'] = $this->costDescription;
        $data['minAmount'] = $this->minAmount;
        $data['maxAmount'] = $this->maxAmount;

        return $data;
    }

    /**
     * @return string|null
     */
    public function getProduct(): ?string
    {
        return $this->product;
    }

    /**
     * @param string $product
     * @return void
     */
    public function setProduct(string $product): void
    {
        $this->product = $product;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string|null
     */
    public function getLongTitle(): ?string
    {
        return $this->longTitle;
    }

    /**
     * @param string $longTitle
     * @return void
     */
    public function setLongTitle(string $longTitle): void
    {
        $this->longTitle = $longTitle;
    }

    /**
     * @return string|null
     */
    public function getStartsAt(): ?string
    {
        return $this->startsAt;
    }

    /**
     * @param string $startsAt
     * @return void
     */
    public function setStartsAt(string $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    /**
     * @return string|null
     */
    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    /**
     * @param string $endsAt
     * @return void
     */
    public function setEndsAt(string $endsAt): void
    {
        $this->endsAt = $endsAt;
    }

    /**
     * @return string|null
     */
    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    /**
     * @param string $campaign
     * @return void
     */
    public function setCampaign(string $campaign): void
    {
        $this->campaign = $campaign;
    }

    /**
     * @return string|null
     */
    public function getClaim(): ?string
    {
        return $this->claim;
    }

    /**
     * @param string $claim
     * @return void
     */
    public function setClaim(string $claim): void
    {
        $this->claim = $claim;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string $icon
     * @return void
     */
    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    /**
     * @return string|null
     */
    public function getCostDescription(): ?string
    {
        return $this->costDescription;
    }

    /**
     * @param string $costDescription
     * @return void
     */
    public function setCostDescription(string $costDescription): void
    {
        $this->costDescription = $costDescription;
    }

    /**
     * @return int|null
     */
    public function getMinAmount(): ?int
    {
        return $this->minAmount;
    }

    /**
     * @param int $minAmount
     * @return void
     */
    public function setMinAmount(int $minAmount): void
    {
        $this->minAmount = $minAmount;
    }

    /**
     * @return int|null
     */
    public function getMaxAmount(): ?int
    {
        return $this->maxAmount;
    }

    /**
     * @param int $maxAmount
     * @return void
     */
    public function setMaxAmount(int $maxAmount): void
    {
        $this->maxAmount = $maxAmount;
    }
}
