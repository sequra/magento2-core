<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api\Builder;

use Sequra\Core\Model\Api\AbstractBuilder;

class Order extends AbstractBuilder
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;


    protected $_shippingAddress;

    public function __construct(
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($orderFactory,
            $productRepository,
            $urlBuilder,
            $scopeConfig,
            $localeResolver);
        $this->_customerSession = $customerSession;
    }

    public function setOrder(\Magento\Framework\Model\AbstractModel $order)
    {
        $this->_order = $order;
        return $this;
    }

    public function build($state = '', $sendRef = false)
    {
        $order = array(
            'merchant' => $this->merchant(),
            'cart' => $this->cartWithItems(),
            'delivery_address' => $this->deliveryAddress(),
            'invoice_address' => $this->invoiceAddress(),
            'customer' => $this->customer(),
            'gui' => $this->gui(),
            'platform' => $this->platform(),
            'state' => $state
        );
        $order = $this->fixRoundingProblems($order);
        if ($sendRef) {
            $order['merchant_reference'] = array(
                'order_ref_1' => $this->_order->getReservedOrderId(),
                'order_ref_2' => $this->_order->getId()
            );
        }

        return $order;
    }

    public function merchant()
    {
        $ret = parent::merchant();
        $id = $this->_order->getId();
        $ret['notify_url'] = $this->_urlBuilder->getUrl('sequra/ipn');
        $ret['notification_parameters'] = array(
            'id' => $id,
            'method' => $this->_order->getPayment()->getMethod(),
            'signature' => $this->sign($id)
        );
        $ret['return_url'] = $this->_urlBuilder->getUrl('sequra/comeback', ['quote_id' => $id]);
        $ret['abort_url'] = $this->_urlBuilder->getUrl('sequra/abort');

        return $ret;
    }

    public function cartWithItems()
    {
        $data = array();
        $data['delivery_method'] = $this->getDeliveryMethod();
        $data['gift'] = false;
        $data['currency'] = $this->_order->getQuoteCurrencyCode();//$this->_order->getOrderCurrencyCode();
        $data['created_at'] = $this->_order->getCreatedAt();
        $data['updated_at'] = $this->_order->getUpdatedAt();
        $data['cart_ref'] = $this->_order->getId();//$this->_order->getQuoteId();
        $data['order_total_with_tax'] = self::integerPrice($this->_order->getGrandTotal());
        $data['order_total_without_tax'] = $data['order_total_with_tax'];
        $data['items'] = $this->items($this->_order);

        return $data;
    }

    public function deliveryAddress()
    {
        $address = $this->_order->getShippingAddress();
        if ('' == $address->getFirstname()) {
            $address = $this->_order->getBillingAddress();
        }
        $extra = array();
        /*Specific for biebersdorf_customerordercomment extension*/
        if ('' != $this->_order->getData('biebersdorf_customerordercomment')) {
            $extra['extra'] = $this->_order->getData('biebersdorf_customerordercomment');
        }

        return array_merge($extra, $this->address($address));
    }

    public function invoiceAddress()
    {
        $address = $this->_order->getBillingAddress();

        return $this->address($address);
    }

    public function customer()
    {
        $data = parent::customer();
        $data['language_code'] = self::notNull($this->_localeResolver->getLocale());
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $data['ip_number'] = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $data['ip_number'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $data['ip_number'] = $_SERVER['REMOTE_ADDR'];
        }
        $data['user_agent'] = $_SERVER["HTTP_USER_AGENT"];
        $data['logged_in'] = (1 == $this->_customerSession->isLoggedIn());

        if ($data['logged_in']) {
            $customer = $this->_customerSession->getCustomer();
            $data['created_at'] = self::dateOrBlank($customer->getCreatedAt());
            $data['updated_at'] = self::dateOrBlank($customer->getUpdatedAt());
            $data['date_of_birth'] = self::dateOrBlank($customer->getDob());
            $data['previous_orders'] = self::getPreviousOrders($customer->getId());
        }

        return $data;
    }

    public function getPreviousOrders($customerID)
    {
        $order_model = $this->_orderFactory->create();
        $orderCollection = $order_model->getCollection()->addFieldToFilter('customer_id',
            array('eq' => array($customerID)));
        $orders = array();
        if ($orderCollection) {
            foreach ($orderCollection AS $order_row) {
                $order = array();
                $order['amount'] = self::integerPrice($order_row->getData('grand_total'));
                $order['currency'] = $order_row->getData('order_currency_code');
                $order['created_at'] = str_replace(' ', 'T', $order_row->getData('created_at'));
                $orders[] = $order;
            }
        }

        return $orders;
    }

    public function getShippingInclTax()
    {
        return $this->_order->getShippingAddress()->getShippingInclTax();
    }

    public function getShippingMethod()
    {
        return $this->_order->getShippingAddress()->getShippingMethod();
    }

    public function productItem()
    {
        $items = array();
        foreach ($this->_order->getAllVisibleItems() as $itemOb) {
            $item = array();
            $item["reference"] = self::notNull($itemOb->getSku());
            $item["name"] = $itemOb->getName() ? self::notNull($itemOb->getName()) : self::notNull($itemOb->getSku());
            $item["downloadable"] = ($itemOb->getIsVirtual() ? true : false);

            $qty = $itemOb->getQty();
            if ((int)$qty == $qty) {
                $item["quantity"] = (int)$qty;
                $item["price_without_tax"] = $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["tax_rate"] = 0;
                $item["total_without_tax"] = $item["total_with_tax"] = self::integerPrice(self::notNull($itemOb->getRowTotalInclTax()));
            } else { //Fake qty and unitary price
                $item["quantity"] = 1;
                $item["tax_rate"] = 0;
                $item["price_without_tax"] = $item["price_with_tax"] = $item["total_without_tax"] = $item["total_with_tax"] = self::integerPrice(self::notNull($itemOb->getRowTotalInclTax()));
            }

            $product = $this->_productRepository->getById($itemOb->getProductId());
            $items[] = array_merge($item, $this->fillOptionalProductItemFields($product));
        }

        return $items;
    }

    public function getObjWithCustomerData()
    {
        if ($this->_customerSession->isLoggedIn()) {
            return $this->_customerSession->getCustomer();
        }
        return $this->_order->getBillingAddress();
    }

}
