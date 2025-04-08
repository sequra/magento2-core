<?php

namespace Sequra\Core\DataAccess\Entities;

use SeQura\Core\Infrastructure\ORM\Configuration\EntityConfiguration;
use SeQura\Core\Infrastructure\ORM\Configuration\IndexMap;
use SeQura\Core\Infrastructure\ORM\Entity;

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
     * Get product identifier.
     *
     * @return string|null
     */
    public function getProduct(): ?string
    {
        return $this->product;
    }

    /**
     * Set product identifier.
     *
     * @param string $product
     *
     * @return void
     */
    public function setProduct(string $product): void
    {
        $this->product = $product;
    }

    /**
     * Get payment method title.
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set payment method title.
     *
     * @param string $title
     *
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * Get long title of the payment method.
     *
     * @return string|null
     */
    public function getLongTitle(): ?string
    {
        return $this->longTitle;
    }

    /**
     * Set long title of the payment method.
     *
     * @param string $longTitle
     *
     * @return void
     */
    public function setLongTitle(string $longTitle): void
    {
        $this->longTitle = $longTitle;
    }

    /**
     * Get start date of payment method availability.
     *
     * @return string|null
     */
    public function getStartsAt(): ?string
    {
        return $this->startsAt;
    }

    /**
     * Set start date of payment method availability.
     *
     * @param string $startsAt
     *
     * @return void
     */
    public function setStartsAt(string $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    /**
     * Get end date of payment method availability.
     *
     * @return string|null
     */
    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    /**
     * Set end date of payment method availability.
     *
     * @param string $endsAt
     *
     * @return void
     */
    public function setEndsAt(string $endsAt): void
    {
        $this->endsAt = $endsAt;
    }

    /**
     * Get campaign identifier.
     *
     * @return string|null
     */
    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    /**
     * Set campaign identifier.
     *
     * @param string $campaign
     *
     * @return void
     */
    public function setCampaign(string $campaign): void
    {
        $this->campaign = $campaign;
    }

    /**
     * Get payment claim text.
     *
     * @return string|null
     */
    public function getClaim(): ?string
    {
        return $this->claim;
    }

    /**
     * Set payment claim text.
     *
     * @param string $claim
     *
     * @return void
     */
    public function setClaim(string $claim): void
    {
        $this->claim = $claim;
    }

    /**
     * Get payment method description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set payment method description.
     *
     * @param string $description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Get payment method icon URL.
     *
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Set payment method icon URL.
     *
     * @param string $icon
     *
     * @return void
     */
    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    /**
     * Get cost description text.
     *
     * @return string|null
     */
    public function getCostDescription(): ?string
    {
        return $this->costDescription;
    }

    /**
     * Set cost description text.
     *
     * @param string $costDescription
     *
     * @return void
     */
    public function setCostDescription(string $costDescription): void
    {
        $this->costDescription = $costDescription;
    }

    /**
     * Get minimum amount for payment method.
     *
     * @return int|null
     */
    public function getMinAmount(): ?int
    {
        return $this->minAmount;
    }

    /**
     * Set minimum amount for payment method.
     *
     * @param int $minAmount
     *
     * @return void
     */
    public function setMinAmount(int $minAmount): void
    {
        $this->minAmount = $minAmount;
    }

    /**
     * Get maximum amount for payment method.
     *
     * @return int|null
     */
    public function getMaxAmount(): ?int
    {
        return $this->maxAmount;
    }

    /**
     * Set maximum amount for payment method.
     *
     * @param int $maxAmount
     *
     * @return void
     */
    public function setMaxAmount(int $maxAmount): void
    {
        $this->maxAmount = $maxAmount;
    }
}
