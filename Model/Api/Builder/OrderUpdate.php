<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api\Builder;

use Sequra\Core\Model\Api\AbstractBuilder;
use Sequra\Core\Model\Api\BuilderInterface;
use Sequra\PhpClient\Helper;

class OrderUpdate extends AbstractBuilder
{
    protected $shippingAddress;

    public function build():BuilderInterface
    {
        $this->data = [
            'merchant' => $this->merchant(),
            'shipped_cart' => $this->shippedCart(),
            'unshipped_cart' => $this->unshippedCart(),
            'delivery_address' => $this->deliveryAddress(),
            'invoice_address' => $this->invoiceAddress(),
            'customer' => $this->customer(),
            'platform' => $this->platform(),
        ];
        return $this;
    }

    public function shippedCart()
    {
        $data = [];
        $data['currency'] = $this->order->getOrderCurrencyCode()?$this->order->getOrderCurrencyCode():'EUR';
        $data['delivery_method'] = $this->getDeliveryMethod();
        $data['gift'] = false;
        $data['items'] = $this->items();

        if (count($data['items']) > 0) {
            $totals = Helper::totals($data);
            $data['order_total_with_tax'] = $totals['with_tax'];
        } else {
            $data['order_total_with_tax'] = 0;
        }

        return $data;
    }

    public function unshippedCart()
    {
        $data = [];
        $data['currency'] = $this->order->getOrderCurrencyCode()?$this->order->getOrderCurrencyCode():'EUR';
        $data['items'] = [];
        $unshipped_discount = 0;
        foreach ($this->order->getAllVisibleItems() as $itemOb) {
            if (is_null($itemOb->getProductId())) {
                continue;
            }
            $item = [];
            $item["reference"] = self::notNull($itemOb->getSku());
            $item["name"] = self::notNull($itemOb->getName());
            $item["downloadable"] = ($itemOb->getIsVirtual() ? true : false);
            $qty = $itemOb->getQtyOrdered() - $itemOb->getQtyShipped() - $itemOb->getQtyRefunded();
            if ((int)$qty == $qty) {
                $item["quantity"] = $qty;
                $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["total_with_tax"] = self::integerPrice(
                    $item["quantity"] * self::notNull($itemOb->getPriceInclTax())
                );
            } else {
                $item["quantity"] = 1;
                $item["total_with_tax"] =
                $item["price_with_tax"] = self::integerPrice(self::notNull($qty * $itemOb->getPriceInclTax()));
            }
            $product = $this->productRepository->getById($itemOb->getProductId());
            if ($item["quantity"] > 0) {
                $data['items'][] = array_merge($item, $this->fillOptionalProductItemFields($product));
            }
            $discount = $itemOb->getDiscountAmount()*$qty/$itemOb->getQtyOrdered();
            if (!$this->getGlobalConfigData(\Magento\Tax\Model\Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX)) {
                $discount *= ( 1 + $itemOb->getTaxPercent() / 100 );
            }
            $unshipped_discount -=$discount;
        }
        if ($unshipped_discount < 0) {
            $item = [];
            $item["type"] = "discount";
            $item["reference"] = self::notNull($this->order->getCouponCode());
            $item["name"] = 'Descuento pendiente';
            $item["total_with_tax"] = self::integerPrice($unshipped_discount);
            $data['items'][] = $item;
        }
        $totals = Helper::totals($data);
        $data['order_total_with_tax'] = $totals['with_tax'];
        return $data;
    }

    public function deliveryAddress()
    {
        $address = $this->order->getShippingAddress();
        if ('' == $address->getFirstname()) {
            $address = $this->order->getBillingAddress();
        }

        return $this->address($address);
    }

    public function invoiceAddress()
    {
        $address = $this->order->getBillingAddress();

        return $this->address($address);
    }

    public function productItem()
    {
        $items = [];
        foreach ($this->order->getAllVisibleItems() as $itemOb) {
            if (is_null($itemOb->getProductId()) || $itemOb->getQtyShipped() <= 0) {
                continue;
            }
            try {
                $product = $this->productRepository->getById($itemOb->getProductId());
            } catch (Exception $e) {
                $this->logger->addError(
                    'Can not get product for id: ' .
                    $itemOb->getProductId() .
                    '  ' . $e->getMessage()
                );
                continue;
            }

            $item = $this->fillOptionalProductItemFields($product);
            $item["reference"] = self::notNull($itemOb->getSku());
            $item["name"] = $itemOb->getName() ? self::notNull($itemOb->getName()) : self::notNull($itemOb->getSku());
            $item["downloadable"] = ($itemOb->getIsVirtual() ? true : false);
            //Just in case any shipped itme has been returned
            $qty = min($itemOb->getQtyShipped(), $itemOb->getQtyOrdered() - $itemOb->getQtyRefunded());
            if ((int)$qty == $qty) {
                $item["quantity"] = (int)$qty;
                $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["total_with_tax"] = self::integerPrice(
                    $item["quantity"] * self::notNull($itemOb->getPriceInclTax())
                );
            } else {//Fake qty and unit price
                $item["quantity"] = 1;
                $item["total_with_tax"] =
                $item["price_with_tax"] = self::integerPrice(self::notNull($qty * $itemOb->getPriceInclTax()));
            }
            $items[] = $item;
        }
        return $items;
    }

    public function getDiscountInclTax()
    {
        $discount_with_tax = 0;
        foreach ($this->order->getAllItems() as $item) {
            $discount = $item->getDiscountAmount()*$item->getQtyShipped()/$item->getQtyOrdered();            ;
            if (!$this->getGlobalConfigData(\Magento\Tax\Model\Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX)) {
                $discount *= ( 1 + $item->getTaxPercent() / 100 );
            }
            $discount_with_tax += self::integerPrice($discount);
        }
        return -1*$discount_with_tax;
    }

    public function getShippingInclTax()
    {
        return $this->order->getShippingInclTax() - $this->order->getShippingRefunded() - $this->order->getShippingTaxRefunded();
    }

    public function getShippingMethod()
    {
        return $this->order->getShippingMethod();
    }

    public function getObjWithCustomerData()
    {
        return $this->order->getBillingAddress();
    }
}
