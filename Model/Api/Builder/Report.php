<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api\Builder;

use Sequra\Core\Model\Api\AbstractBuilder;
use Sequra\Core\Model\Api\BuilderInterface;
use Sequra\PhpClient\Helper;

class Report extends AbstractBuilder
{
    /**
     * Order object
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    protected $builtData;
    protected $sequraOrders = null;
    protected $orders = [];
    protected $currentshipment = null;
    protected $ids = [];
    protected $brokenOrders = [];
    protected $stats = [];
    protected $storeId = null;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct(
            $orderFactory,
            $productRepository,
            $urlBuilder,
            $scopeConfig,
            $localeResolver,
            $moduleResource,
            $logger
        );
    }

    public function getOrderCount()
    {
        return count($this->ids);
    }

    public function setOrdersAsSent()
    {
        foreach ($this->sequraOrders as $order) {
            $order->setData('sequra_order_send', 0);
            $this->orderRepository->save($order);
        }
    }

    public function build():BuilderInterface
    {
        $this->getOrders();
        if(!$this->limit || $this->getConfigData('reporting')){
            $this->getStats();
        }
        $this->builtData = [
            'merchant' => $this->merchant(),
            'orders' => $this->orders,
            'broken_orders' => $this->brokenOrders,
            'statistics' => ['orders' => $this->stats],
            'platform' => self::platform()
        ];
        return $this;
    }

    protected function getOrders()
    {
        $this->getSequraOrders();
        $this->orders = [];
        foreach ($this->sequraOrders as $order) {
            //needed to populate related objects e.g.: customer
            $this->order = $this->orderRepository->get($order->getId());
            $this->orders[] = $this->orderWithItems($this->order);
            $this->ids[] = $this->order->getId();
            if (method_exists($this->order, 'addCommentToStatusHistory')) {
                $this->order->addCommentToStatusHistory('Envío informado a SeQura');
            } else {
                $this->order->addStatusHistoryComment('Envío informado a SeQura');
            }
        }
        $this->getBrokenOrders();
    }

    /**
     * Loads orders paid with sequra and not sent in previous delivery reports
     *
     * @return null
     */
    protected function getSequraOrders()
    {
        $collection = $this->orderCollectionFactory->create()->addFieldToSelect([
            'entity_id',//load minimun fields, anyway later, we need to populate all and load related objects.
            'increment_id',
            'state',
            'status',
        ])->addFieldToFilter(
            'sequra_order_send',
            ['eq' => 1]
        )->addFieldToFilter(
            'main_table.store_id',
            ['eq' => $this->storeId]
        );
        /* join with payment table */
        $collection->getSelect()
            ->join(
                ["sop" => $collection->getTable('sales_order_payment')],
                'main_table.entity_id = sop.parent_id',
                ['method']
            )
            ->join(
                ['sp' => $collection->getTable('sales_shipment')],
                'main_table.entity_id = sp.order_id and main_table.store_id = sp.store_id',
                ''
            )
            ->where('sop.method like ?', 'sequra\_%')
            ->distinct(true);
        if ($this->limit) {
            $collection->getSelect()->limit($this->limit);
        }
        $this->sequraOrders = $collection;

        return $this->sequraOrders;
    }

    public function orderWithItems($order)
    {
        $this->currentshipment = $order->getShipmentsCollection()->getFirstItem();
        $this->order = $order;
        $aux['sent_at'] = self::dateOrBlank($this->currentshipment->getCreatedAt());
        $aux['state'] = "delivered";
        $aux['delivery_address'] = $this->deliveryAddress();
        $aux['invoice_address'] = $this->invoiceAddress();
        $aux['customer'] = $this->customer();
        $aux['cart'] = $this->shipmentCart();
        $aux['remaining_cart'] = $this->orderRemainingCart();
        $aux['merchant_reference'] = $this->orderMerchantReference($order);

        return $this->fixRoundingProblems($aux);
    }

    public function deliveryAddress()
    {
        return self::address($this->order->getShippingAddress());
    }

    public function invoiceAddress()
    {
        return self::address($this->order->getBillingAddress());
    }

    public function customer()
    {
        $data = parent::customer();
        $customer_id = $this->order->getCustomerId();
        if ($customer_id) {
            $customer = $this->customerRepository->getById($customer_id);
            $data['email'] = self::notNull($customer->getEmail());
            $data['ref'] = self::notNull($customer_id);
        }
        return $data;
    }

    public function shipmentCart()
    {
        $data = [];
        $data['currency'] = $this->order->getOrderCurrencyCode()?$this->order->getOrderCurrencyCode():'EUR';
        $data['delivery_method'] = $this->getDeliveryMethod();
        $data['gift'] = false;
        $data['items'] = $this->items();

        if (count($data['items']) > 0) {
            $totals = Helper::totals($data);
            $data['order_total_without_tax'] = $data['order_total_with_tax'] = $totals['with_tax'];
        }

        return $data;
    }

    public function orderRemainingCart()
    {
        $data = [];
        $data['currency'] = $this->order->getOrderCurrencyCode()?$this->order->getOrderCurrencyCode():'EUR';
        $data['items'] = [];
        $remaining_discount = 0;
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
                $item["price_without_tax"] =
                $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
                $item["total_without_tax"] = $item["total_with_tax"] = $item["quantity"] * $item["price_with_tax"];
            } else {
                $item["quantity"] = 1;
                $item["total_without_tax"] =
                $item["total_with_tax"] =
                $item["price_without_tax"] =
                $item["price_with_tax"] = self::integerPrice(self::notNull($qty * $itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
            }
            $product = $this->productRepository->getById($itemOb->getProductId());
            if ($item["quantity"] > 0) {
                $data['items'][] = array_merge($item, $this->fillOptionalProductItemFields($product));
            }
            $discount = $itemOb->getDiscountAmount()*$qty/$itemOb->getQtyOrdered();
            if (!$this->getGlobalConfigData(\Magento\Tax\Model\Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX)) {
                $discount *= ( 1 + $itemOb->getTaxPercent() / 100 );
            }
            $remaining_discount -=$discount;
        }
        if ($remaining_discount < 0) {
            $item = [];
            $item["type"] = "discount";
            $item["reference"] = self::notNull($this->order->getCouponCode());
            $item["name"] = 'Descuento pendiente';
            $item["total_without_tax"] = $item["total_with_tax"] = self::integerPrice($remaining_discount);
            $data['items'][] = $item;
        }
        $totals = Helper::totals($data);
        $data['order_total_without_tax'] = $totals['without_tax'];
        $data['order_total_with_tax'] = $totals['with_tax'];
        return $data;
    }

    public function orderMerchantReference($order)
    {
        $data['order_ref_1'] = $order->getOriginalIncrementId()??$order->getIncrementId();
        $data['order_ref_2'] = $order->getId();
        return $data;
    }

    private function getBrokenOrders()
    {
        $cleaned_orders = [];
        $this->brokenOrders = [];
        foreach ($this->orders as $key => $order) {
            if (!Helper::isConsistentCart($order['cart'])) {
                $this->brokenOrders[] = $order;
            } else {
                $cleaned_orders[] = $order;
            }
        }
        $this->orders = $cleaned_orders;
    }

    /**
     * Load stats
     */
    private function getStats()
    {
        $statsCollection = $this->getStatsCollection();
        $this->stats = [];
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
                    case 'paycomet':
                    case 'cc':
                    case 'ccsave':
                        $payment_method_enum = 'CC';
                        break;
                    case \Magento\Paypal\Model\Config::METHOD_BILLING_AGREEMENT:
                    case \Magento\Paypal\Model\Config::METHOD_EXPRESS:
                    case \Magento\Paypal\Model\Config::METHOD_HOSTEDPRO:
                    case \Magento\Paypal\Model\Config::METHOD_PAYFLOWADVANCED:
                    case \Magento\Paypal\Model\Config::METHOD_PAYFLOWLINK:
                    case \Magento\Paypal\Model\Config::METHOD_PAYFLOWPRO:
                    case \Magento\Paypal\Model\Config::METHOD_WPP_DIRECT:
                    case \Magento\Paypal\Model\Config::METHOD_WPP_PE_EXPRESS:
                    case \Magento\Paypal\Model\Config::METHOD_WPP_BML:
                    case (preg_match('/.*paypal.*/', $payment_method_raw) ? true : false):
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
                    case (preg_match('/sequra.*/', $payment_method_raw) ? true : false):
                        $payment_method_enum = 'SQ';
                        break;
                    default:
                        $payment_method_enum = 'O/' . $payment_method_raw;
                }
            }

            $stat = [
                'completed_at' => $order->getData('created_at'),
                'merchant_reference' => [
                    'order_ref_1' =>
                        $order->getOriginalIncrementId()??$order->getRealOrderId(),
                    'order_ref_2' => $order->getId(),
                ],
                'currency' => $order->getOrderCurrencyCode(),
            ];
            $stattypes = explode(',', $this->getConfigData('specificstattypes'));
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(
                \Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_AMOUNT,
                $stattypes
            )
            ) {
                $stat['amount'] = self::integerPrice($order->getGrandTotal());
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(
                \Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_COUNTRY,
                $stattypes
            )
            ) {
                $stat['country'] = $address->getCountryId();
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(
                \Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_PAYMENT,
                $stattypes
            )
            ) {
                $stat['payment_method'] = $payment_method_enum;
                $stat['payment_method_raw'] = $payment_method_raw;
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || in_array(
                \Sequra\Core\Model\Adminhtml\Source\Specificstattypes::STAT_STATUS,
                $stattypes
            )
            ) {
                $stat['status'] = $status;
                $stat['raw_status'] = $order->getStatus();
            }
            if ('0' == $this->getConfigData('allowspecificstattypes') || count($stattypes) > 0) {
                $this->stats[] = $stat;
            }
        }
    }

    /**
     * Get collection with orders not paid by sequra for statistics
     *
     * @return collection
     */
    private function getStatsCollection()
    {
        $time = time();
        $to = date('Y-m-d H:i:s', $time);
        $lastTime = $time - $this->getConfigData('statsperiod') * 60 * 60 * 24;// 60*60*24*
        $from = date('Y-m-d H:i:s', $lastTime);
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('store_id', $this->storeId)
            ->addFilter('created_at', $from, 'gt')
            ->addFilter('created_at', $to, 'lt')
            ->create();
        $orderResult = $this->orderRepository->getList($criteria);
        return $orderResult->getItems();
    }

    public function getBuiltData()
    {
        return $this->builtData;
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
            $qty = $itemOb->getQtyShipped();
            if ((int)$qty == $qty) {
                $item["quantity"] = (int)$qty;
                $item["price_without_tax"] =
                $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
                $item["total_without_tax"] = $item["total_with_tax"] = $item["quantity"] * $item["price_with_tax"];
            } else {//Fake qty and unit price
                $item["quantity"] = 1;
                $item["total_without_tax"] =
                $item["total_with_tax"] =
                $item["price_without_tax"] =
                $item["price_with_tax"] = self::integerPrice(self::notNull($qty * $itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
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
