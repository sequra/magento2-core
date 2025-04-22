<?php

namespace Sequra\Core\Services\BusinessLogic\Utility;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order\Address as MagentoAddress;
use Magento\Sales\Model\Order as MagentoOrder;
use SeQura\Core\BusinessLogic\Domain\Order\Exceptions\InvalidCartItemsException;
use SeQura\Core\BusinessLogic\Domain\Order\Exceptions\InvalidQuantityException;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Address as SeQuraAddress;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Cart as SeQuraCart;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\DiscountItem;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\HandlingItem;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\Item as SeQuraItem;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\ItemType;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\ProductItem;

class TransformEntityService
{
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * @param SeQuraTranslationProvider $translationProvider
     */
    public function __construct(SeQuraTranslationProvider $translationProvider)
    {
        $this->translationProvider = $translationProvider;
    }

    /**
     * Creates a seQura order address from the magento order data.
     *
     * @param OrderAddressInterface|MagentoAddress $address
     *
     * @return SeQuraAddress
     */
    public function transformAddressToSeQuraOrderAddress($address): SeQuraAddress
    {
        $addressLine1 = $address->getStreet();
        if (is_array($addressLine1)) {
            $addressLine1 = implode("\n", $addressLine1);
        } elseif ($addressLine1 === null) {
            $addressLine1 = '';
        } elseif (!is_string($addressLine1)) {
            $addressLine1 = (string)$addressLine1;
        }
        return new SeQuraAddress(
            $address->getCompany() ?? '',
            $addressLine1,
            '',
            $address->getPostcode(),
            $address->getCity(),
            $address->getCountryId(),
            $address->getFirstname(),
            $address->getLastname(),
            $address->getTelephone(),
            null,
            $address->getRegion(),
            null,
            $address->getVatId()
        );
    }

    /**
     * Creates the seQura cart from the magento order data.
     *
     * @param MagentoOrder $orderData
     * @param bool $isShipped
     *
     * @return SeQuraCart
     *
     * @throws InvalidCartItemsException
     * @throws InvalidQuantityException
     * @throws LocalizedException
     */
    public function transformOrderCartToSeQuraCart(MagentoOrder $orderData, bool $isShipped): SeQuraCart
    {
        return new SeQuraCart(
            ($orderData->getOrderCurrencyCode() ?? ''),
            false,
            $this->transformOrderItemsToSeQuraCartItems($orderData, $isShipped)
        );
    }

    /**
     * Creates the seQura order cart items from the magento order data.
     *
     * @param MagentoOrder $orderData
     * @param bool $isShipped
     *
     * @return SeQuraItem[]
     *
     * @throws InvalidQuantityException
     * @throws LocalizedException
     */
    public function transformOrderItemsToSeQuraCartItems(MagentoOrder $orderData, bool $isShipped): array
    {
        $items = [];
        $orderItemsTotal = 0;
        $isUnshippedOrFullyShipped = true;
        /** @var MagentoOrder\Item $orderItem */
        foreach ($orderData->getAllVisibleItems() as $orderItem) {
            $orderItemsTotal += self::transformPrice($orderItem->getRowTotalInclTax() ?? 0);
            $orderedQty = $orderItem->getQtyOrdered() ? (int)$orderItem->getQtyOrdered() : 0;
            $shippedQty = $orderItem->getQtyShipped() ? (int)$orderItem->getQtyShipped() : 0;
            $refundedQty = $orderItem->getQtyRefunded() ? (int)$orderItem->getQtyRefunded() : 0;
            if (($shippedQty + $refundedQty) > $orderedQty) {
                throw new LocalizedException(
                    $this->translationProvider->translate('sequra.error.invalidShipRefundQuantity')
                );
            }

            $quantity = $isShipped ? $shippedQty : $orderedQty - $shippedQty - $refundedQty;

            if ($isShipped && ($orderedQty - $refundedQty) !== $shippedQty) {
                $isUnshippedOrFullyShipped = false;
            }

            if (($isShipped && $shippedQty <= 0) || (!$isShipped && $shippedQty >= ($orderedQty - $refundedQty))) {
                continue;
            }

            $product = $orderItem->getProduct();
            $items[] = ProductItem::fromArray([
                'type' => ItemType::TYPE_PRODUCT,
                'reference' => $orderItem->getSku(),
                'name' => $orderItem->getName(),
                'price_with_tax' => self::transformPrice($orderItem->getPriceInclTax() ?? 0),
                'quantity' => $quantity,
                'total_with_tax' => self::transformPrice($orderItem->getPriceInclTax() ?? 0) * $quantity,
                'downloadable' => (bool)$orderItem->getIsVirtual(),
                'description' => $orderItem->getDescription(),
                'category' => ($product && $product->getCategory()) ? $product->getCategory()->getName() : '',
                'product_id' => $product ? $product->getId() : null,
                'url' => $product ? $product->getProductUrl() : null
            ]);
        }

        $refundedShippingAmount = $orderData->getShippingRefunded() ?
        self::transformPrice($orderData->getShippingRefunded()) : 0;
        $shippingAmount = $orderData->getShippingInclTax() ? self::transformPrice($orderData->getShippingInclTax()) : 0;
        $totalShipmentCost = $shippingAmount - $refundedShippingAmount;
        $orderItemsTotal += $shippingAmount;
        if ($isUnshippedOrFullyShipped && $totalShipmentCost > 0 && !self::isCartEmpty($items)) {
            $items[] = HandlingItem::fromArray([
                'type' => ItemType::TYPE_HANDLING,
                'reference' => 'shipping cost',
                'name' => 'Shipping cost',
                'total_with_tax' => $totalShipmentCost,
            ]);
        }

        $refundedDiscountAmount = $orderData->getDiscountRefunded() ?
        self::getTotalDiscountAmount($orderData, true) : 0;
        $discountAmount = $orderData->getDiscountAmount() ? self::getTotalDiscountAmount($orderData) : 0;
        $totalDiscount = $discountAmount - $refundedDiscountAmount;
        $orderItemsTotal += $discountAmount;
        if ($isUnshippedOrFullyShipped && $totalDiscount < 0 && !self::isCartEmpty($items)) {
            $items[] = DiscountItem::fromArray([
                'type' => ItemType::TYPE_DISCOUNT,
                'reference' => 'discount',
                'name' => 'Discount',
                'total_with_tax' => $totalDiscount,
            ]);
        }

        if ($orderData->getAdjustmentPositive()) {
            $orderItemsTotal += self::transformPrice($orderData->getAdjustmentPositive());
        }

        if ($orderData->getAdjustmentNegative()) {
            $orderItemsTotal += self::transformPrice(-$orderData->getAdjustmentNegative());
        }

        $diff = self::transformPrice($orderData->getGrandTotal()) - $orderItemsTotal;

        if ($diff < 0 && $isUnshippedOrFullyShipped && !self::isCartEmpty($items)) {
            $items[] = new DiscountItem('additional_discount', 'discount', $diff);
        }

        if ($diff > 0 && $isUnshippedOrFullyShipped && !self::isCartEmpty($items)) {
            $items[] = new HandlingItem('additional_handling', 'surcharge', $diff);
        }

        /**
         * @var float $cartTotal
         */
        $cartTotal = array_reduce($items, static function ($sum, $item) {
            return $sum + $item->getTotalWithTax();
        }, 0);

        if ($cartTotal < 0) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.invalidRefundAmount'));
        }

        return $items;
    }

    // TODO: Static method cannot be intercepted and its use is discouraged.
    // phpcs:disable Magento2.Functions.StaticFunction.StaticFunction
    
    /**
     * Transform price to format.
     *
     * @param int|float $price
     *
     * @return int
     */
    public static function transformPrice($price): int
    {
        return (int)round($price * 100);
    }

    /**
     * Get the valid total discount amount for SeQura.
     *
     * @param MagentoOrder $orderData
     * @param bool $isRefunded
     *
     * @return int
     */
    private static function getTotalDiscountAmount(MagentoOrder $orderData, bool $isRefunded = false): int
    {
        $totalDiscount = 0;

        /** @var MagentoOrder\Item $item */
        foreach ($orderData->getAllVisibleItems() as $item) {
            $discount = $isRefunded ? $item->getDiscountRefunded() : $item->getDiscountAmount();

            // Needed because of tax difference on SeQura and Magento
            if (self::isTaxedAfterDiscount($item) && !self::doesPriceIncludeTax($orderData)) {
                $discount *= (1 + $item->getTaxPercent() / 100);
            }

            $totalDiscount += self::transformPrice($discount ?? 0);
        }

        return -1 * $totalDiscount;
    }

    /**
     * Returns true if the order tax was applied after the discount.
     *
     * @param MagentoOrder\Item $orderItem
     *
     * @return bool
     */
    private static function isTaxedAfterDiscount(MagentoOrder\Item $orderItem): bool
    {
        if (!$orderItem->getTaxAmount() || !$orderItem->getTaxPercent() ||
            self::transformPrice($orderItem->getTaxAmount()) === 0 ||
            self::transformPrice($orderItem->getTaxPercent()) === 0
        ) {
            return false;
        }

        $totalIncludingTax = self::transformPrice($orderItem->getRowTotalInclTax() ?? 0);
        $taxAmount = self::transformPrice($orderItem->getTaxAmount());
        return $totalIncludingTax !== ($taxAmount * 100) / $orderItem->getTaxPercent();
    }

    /**
     * Returns true if catalog prices include tax.
     *
     * @param MagentoOrder $order
     *
     * @return bool
     */
    private static function doesPriceIncludeTax(MagentoOrder $order): bool
    {
        return self::transformPrice($order->getGrandTotal()) -
            self::transformPrice($order->getSubtotalInclTax() ?? 0) -
            self::transformPrice($order->getShippingInclTax() ?? 0) -
            self::transformPrice($order->getDiscountAmount() ?? 0) === 0;
    }

    /**
     * Checks if there are cart items of type product.
     *
     * @param SeQuraItem[] $items
     *
     * @return bool
     */
    private static function isCartEmpty(array $items): bool
    {
        return empty(array_filter($items, static function ($item) {
            return $item->getType() === ItemType::TYPE_PRODUCT;
        }));
    }

    // phpcs:enable Magento2.Functions.StaticFunction.StaticFunction
}
