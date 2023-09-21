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
     * Creates a seQura order address from the magento order data.
     *
     * @param OrderAddressInterface|MagentoAddress $address
     *
     * @return SeQuraAddress
     */
    public static function transformAddressToSeQuraOrderAddress($address): SeQuraAddress
    {
        return new SeQuraAddress(
            $address->getCompany() ?? '',
            $address->getStreet() ? implode("\n", $address->getStreet()) : ($address->getStreet() ?? ''),
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
    public static function transformOrderCartToSeQuraCart(MagentoOrder $orderData, bool $isShipped): SeQuraCart
    {
        return new SeQuraCart(
            $orderData->getOrderCurrencyCode(),
            false,
            self::transformOrderItemsToSeQuraCartItems($orderData, $isShipped)
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
    public static function transformOrderItemsToSeQuraCartItems(MagentoOrder $orderData, bool $isShipped): array
    {
        $items = [];
        $isUnshippedOrFullyShipped = true;
        /** @var MagentoOrder\Item $orderItem */
        foreach ($orderData->getAllVisibleItems() as $orderItem) {
            $orderedQty = $orderItem->getQtyOrdered() ? (int)$orderItem->getQtyOrdered() : 0;
            $shippedQty = $orderItem->getQtyShipped() ? (int)$orderItem->getQtyShipped() : 0;
            $refundedQty = $orderItem->getQtyRefunded() ? (int)$orderItem->getQtyRefunded() : 0;
            if (($shippedQty + $refundedQty) > $orderedQty) {
                throw new LocalizedException(__('Invalid quantity to ship/refund. You cannot ship or refund items that have already been either shipped or refunded.'));
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
                'price_with_tax' => self::transformPrice($orderItem->getPriceInclTax()),
                'quantity' => $quantity,
                'total_with_tax' => self::transformPrice($orderItem->getPriceInclTax()) * $quantity,
                'downloadable' => (bool)$orderItem->getIsVirtual(),
                'description' => $orderItem->getDescription(),
                'category' => ($product && $product->getCategory()) ? $product->getCategory()->getName() : '',
                'product_id' => $product ? $product->getId() : null,
                'url' => $product ? $product->getProductUrl() : null
            ]);
        }

        $shippingAmount = $orderData->getShippingAmount();
        if ($isUnshippedOrFullyShipped && $shippingAmount && $shippingAmount > 0 && !self::isCartEmpty($items)) {
            $items[] = HandlingItem::fromArray([
                'type' => ItemType::TYPE_HANDLING,
                'reference' => 'shipping cost',
                'name' => 'Shipping cost',
                'total_with_tax' => self::transformPrice($orderData->getShippingInclTax()),
            ]);
        }

        $discountAmount = $orderData->getDiscountAmount();
        if ($isUnshippedOrFullyShipped && $discountAmount && $discountAmount < 0 && !self::isCartEmpty($items)) {
            $items[] = DiscountItem::fromArray([
                'type' => ItemType::TYPE_DISCOUNT,
                'reference' => 'discount',
                'name' => 'Discount',
                'total_with_tax' => self::transformPrice($orderData->getDiscountAmount()),
            ]);
        }

        return $items;
    }

    /**
     * Transform price to format.
     *
     * @param int|float $price
     *
     * @return int
     */
    public static function transformPrice($price): int
    {
        return (int)($price * 100);
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
}
