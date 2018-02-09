<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api\Builder;

use Sequra\Core\Model\Api\AbstractBuilder;
use Sequra\PhpClient\Client;
use Sequra\PhpClient\Helper;

class Report extends AbstractBuilder
{
    /**
     * Order object
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    protected $_builtData;
    protected $_sequraOrders = null;
    protected $_orders = array();
    protected $_currentshipment = null;
    protected $_ids = array();
    protected $_brokenorders = array();
    protected $_stats = array();
    protected $_store_id = null;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    ) {
        $this->_orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($orderFactory, $productRepository, $urlBuilder, $scopeConfig, $localeResolver);
    }


    public function getOrderCount()
    {
        return count($this->_ids);
    }

    public function setOrdersAsSent()
    {
        foreach ($this->_sequraOrders as $order) {
            $order->setData('sequra_order_send', 0);
            $this->_orderRepository->save($order);
        }
    }

    public function build($store_id)
    {
        $this->_store_id = $store_id;
        $this->getOrders();
        $this->getStats();
        $this->_builtData = array(
            'merchant' => $this->merchant(),
            'orders' => $this->_orders,
            'broken_orders' => $this->_brokenorders,
            'statistics' => array('orders' => $this->_stats),
            'platform' => self::platform()
        );
    }

    public function getBuiltData()
    {
        return $this->_builtData;
    }

    protected function getOrders()
    {
        $this->getSequraOrders();
        $this->_orders = array();
        foreach ($this->_sequraOrders as $i => $order) {
            if (!$order->getShipmentsCollection()->getTotalCount()) {
                unset($this->_sequraOrders[$i]);
                continue; //ignore if it has no shipments
            }
            $this->_orders[] = $this->orderWithItems($order);
            $this->_ids[] = $order->getId();
            $order->addStatusHistoryComment('Envío informado a SeQura');
        }
        $this->getBrokenOrders();
    }

    /**
     * Loads orders paid with sequra and not sent in previous delivery reports
     * @return null
     */
    protected function getSequraOrders()
    {
        $criteria = $this->_searchCriteriaBuilder
            ->addFilter('sequra_order_send', 1)
            ->create();
        $orderResult = $this->_orderRepository->getList($criteria);
        $this->_sequraOrders = $orderResult->getItems();
        return $this->_sequraOrders;
    }

    /**
     * Load stats
     */
    private function getStats()
    {
        $statsCollection = $this->getStatsCollection();
        $this->_stats = array();
        foreach ($statsCollection as $order) {
            $status = 'processing';
            if ($order->getData('sp_id')) {
                $status = 'shipped';
            }
            switch ($order->getState()) {
                case \Magento\Sales\Model\Order::STATE_CANCELED:
                    $status = 'cancelled';
                    break;
            }
            $address = $order->getBillingAddress();
            if (!is_object($address)) {
                continue;
            }
            $payment_method = $order->getPayment();
            if (is_object($payment_method)) {
                try {
                    $payment_method_raw = $payment_method->getMethodInstance()->getCode();
                } catch (Exception $e) {
                    $payment_method_raw = 'Unknown';
                }
                switch ($payment_method_raw) {
                    case 'ceca':
                    case 'servired':
                    case 'servired_pro':
                    case 'serviredpro':
                    case 'servired_standard':
                    case 'redsys':
                    case 'redsys_standard':
                    case 'iupay':
                    case 'univia':
                    case 'banesto':
                    case 'ruralvia':
                    case 'cuatrob':
                    case 'paytpvcom':
                    case 'cc':
                    case 'ccsave':
                        $payment_method_enum = 'CC';
                        break;
                    case \Magento\Paypal\Model\Config::METHOD_BILLING_AGREEMENT;
                    case \Magento\Paypal\Model\Config::METHOD_EXPRESS:
                    case \Magento\Paypal\Model\Config::METHOD_HOSTEDPRO:
                    case \Magento\Paypal\Model\Config::METHOD_PAYFLOWADVANCED:
                    case \Magento\Paypal\Model\Config::METHOD_PAYFLOWLINK:
                    case \Magento\Paypal\Model\Config::METHOD_PAYFLOWPRO:
                    case \Magento\Paypal\Model\Config::METHOD_WPP_DIRECT:
                    case \Magento\Paypal\Model\Config::METHOD_WPP_PE_EXPRESS:
                    case \Magento\Paypal\Model\Config::METHOD_WPP_BML:
                        $payment_method_enum = 'PP';
                        break;
                    case 'checkmo':
                    case 'banktransfer':
                    case 'trustly':
                        $payment_method_enum = 'TR';
                        break;
                    case 'cashondelivery':
                    case 'cod':
                    case 'i4seur_cashondelivery':
                        $payment_method_enum = 'COD';
                        break;
                    case 'sequrapayment':
                    case 'sequrapartpayment':
                        $payment_method_enum = 'SQ';
                        break;
                    default:
                        $payment_method_enum = 'O/' . $payment_method_raw;
                }
            }

            $stat = array(
                "completed_at" => $order->getData('created_at'),
                "merchant_reference" => array(
                    "order_ref_1" => $order->getOriginalIncrementId() ? $order->getOriginalIncrementId() : $order->getRealOrderId(),
                    "order_ref_2" => $order->getId(),
                )
            );
            $stattypes = explode(',', $this->getConfigData('specificstattypes'));
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(\Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_AMOUNT,
                    $stattypes)
            ) {
                $stat['currency'] = $order->getOrderCurrencyCode();
                $stat['amount'] = self::integerPrice($order->getGrandTotal());
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(\Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_COUNTRY,
                    $stattypes)
            ) {
                $stat['country'] = $address->getCountryId();
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(\Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_PAYMENT,
                    $stattypes)
            ) {
                $stat['payment_method'] = $payment_method_enum;
                $stat['payment_method_raw'] = $payment_method_raw;
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(\Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_STATUS,
                    $stattypes)
            ) {
                $stat['status'] = $status;
                $stat['raw_status'] = $order->getStatus();
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || count($stattypes) > 0) {
                $this->_stats[] = $stat;
            }
        }
    }

    /**
     * Get collection with orders not paid by sequra for statistics
     * @return collection
     */
    private function getStatsCollection()
    {
        $time = time();
        $to = date('Y-m-d H:i:s', $time);
        $lastTime = $time - 604800; // 60*60*24*
        $from = date('Y-m-d H:i:s', $lastTime);
        $criteria = $this->_searchCriteriaBuilder
            ->addFilter('store_id', $this->_store_id)
            ->addFilter('created_at', $from, 'gt')
            ->addFilter('created_at', $to, 'lt')
            ->create();
        $orderResult = $this->_orderRepository->getList($criteria);
        return $orderResult->getItems();
    }

    private function getBrokenOrders()
    {
        $cleaned_orders = array();
        $this->_brokenorders = array();
        foreach ($this->_orders as $key => $order) {
            if (!Helper::isConsistentCart($order['cart'])) {
                $this->_brokenorders[] = $order;
            } else {
                $cleaned_orders[] = $order;
            }
        }
        $this->_orders = $cleaned_orders;
    }

    public function orderWithItems($order)
    {
        $this->_currentshipment = $order->getShipmentsCollection()->getFirstItem();
        $this->_order = $order;
        $aux['sent_at'] = self::dateOrBlank($this->_currentshipment->getCreatedAt());
        $aux['state'] = "delivered";
        $aux['delivery_address'] = $this->deliveryAddress();
        $aux['invoice_address'] = $this->invoiceAddress();
        $aux['customer'] = $this->customer();
        $aux['cart'] = $this->shipmentCart();
        $remainingCart = $this->orderRemainingCart();
        if (!is_null($remainingCart)) {
            $aux['remaining_cart'] = $remainingCart;
        }
        $aux['merchant_reference'] = $this->orderMerchantReference($order);

        return $this->fixRoundingProblems($aux);
    }

    public function shipmentCart()
    {
        $data = array();
        $data['currency'] = $this->_order->getOrderCurrencyCode();
        $data['delivery_method'] = $this->getDeliveryMethod();
        $data['gift'] = false;
        $data['items'] = $this->items($this->_order);

        if (count($data['items']) > 0) {
            $totals = Helper::totals($data);
            $data['order_total_without_tax'] = $data['order_total_with_tax'] = $totals['with_tax'];
        }

        return $data;
    }

    public function productItem()
    {
        $items = array();
        foreach ($this->_order->getAllVisibleItems() as $itemOb) {
            if (is_null($itemOb->getProductId()) || $itemOb->getQtyShipped() <= 0) {
                continue;
            }
            $product = $this->_productRepository->getById($itemOb->getProductId());
            $item = $this->fillOptionalProductItemFields($product);
            $item["reference"] = self::notNull($itemOb->getSku());
            $item["name"] = $itemOb->getName() ? self::notNull($itemOb->getName()) : self::notNull($itemOb->getSku());
            $item["downloadable"] = ($itemOb->getIsVirtual() ? true : false);
            $qty = $itemOb->getQtyShipped();
            if ((int)$qty == $qty) {
                $item["quantity"] = (int)$qty;
                $item["price_without_tax"] = $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
                $item["total_without_tax"] = $item["total_with_tax"] = $item["quantity"] * $item["price_with_tax"];
            } else {//Fake qty and unit price
                $item["quantity"] = 1;
                $item["total_without_tax"] = $item["total_with_tax"] = $item["price_without_tax"] = $item["price_with_tax"] = self::integerPrice(self::notNull($qty * $itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
            }
            $items[] = $item;
        }
        return $items;
    }

    public function orderRemainingCart()
    {
        $data = array();
        $data['items'] = array();
        foreach ($this->_order->getAllVisibleItems() as $itemOb) {
            if (is_null($itemOb->getProductId())) {
                continue;
            }
            $item = array();
            $item["reference"] = self::notNull($itemOb->getSku());
            $item["name"] = self::notNull($itemOb->getName());
            $item["downloadable"] = ($itemOb->getIsVirtual() ? true : false);
            $qty = $itemOb->getQtyOrdered() - $itemOb->getQtyShipped();
            if ((int)$qty == $qty) {
                $item["quantity"] = $qty;
                $item["price_without_tax"] = $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
                $item["total_without_tax"] = $item["total_with_tax"] = $item["quantity"] * $item["price_with_tax"];
            } else {
                $item["quantity"] = 1;
                $item["total_without_tax"] = $item["total_with_tax"] = $item["price_without_tax"] = $item["price_with_tax"] = self::integerPrice(self::notNull($qty * $itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
            }
            $product = $this->_productRepository->getById($itemOb->getProductId());
            if ($item["quantity"] > 0) {
                $data['items'][] = array_merge($item, $this->fillOptionalProductItemFields($product));
            }
        }

        if (count($data['items']) > 0) {
            $totals = Helper::totals($data);
            $data['order_total_without_tax'] = $totals['without_tax'];
            $data['order_total_with_tax'] = $totals['with_tax'];
            return $data;
        }
        return null;
    }

    public function getShippingInclTax()
    {
        return $this->_order->getShippingInclTax();
    }

    public function getShippingMethod()
    {
        return $this->_order->getShippingMethod();
    }

    public function deliveryAddress()
    {
        return self::address($this->_order->getShippingAddress());
    }

    public function invoiceAddress()
    {
        return self::address($this->_order->getBillingAddress());
    }

    public function customer()
    {
        $data = parent::customer();
        $customer_id = $this->_order->getCustomerId();
        if ($customer_id) {
            $customer = $this->customerRepository->getById($customer_id);
            $data['email'] = self::notNull($customer->getEmail());
            $data['ref'] = self::notNull($customer_id);
        }
        return $data;
    }

    public function getObjWithCustomerData()
    {
        return $this->_order->getBillingAddress();
    }

    public function orderMerchantReference($order)
    {
        $data['order_ref_1'] = $order->getOriginalIncrementId() ? $order->getOriginalIncrementId() : $order->getIncrementId();
        return $data;
    }
}
