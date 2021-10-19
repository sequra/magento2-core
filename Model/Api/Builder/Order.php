<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api\Builder;

use Sequra\Core\Model\Api\AbstractBuilder;
use Sequra\Core\Model\Api\BuilderInterface;

class Order extends AbstractBuilder
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    protected $shippingAddress;

    public function __construct(
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct(
            $orderFactory,
            $productRepository,
            $urlBuilder,
            $scopeConfig,
            $localeResolver,
            $moduleResource,
            $logger
        );
        $this->customerSession = $customerSession;
    }

    public function build():BuilderInterface
    {
        $this->data = [
            'merchant' => $this->merchant(),
            'cart' => $this->cartWithItems(),
            'delivery_address' => $this->deliveryAddress(),
            'invoice_address' => $this->invoiceAddress(),
            'customer' => $this->customer(),
            'gui' => $this->gui(),
            'platform' => $this->platform(),
            'state' => ''
        ];
        $this->data = $this->fixRoundingProblems($this->data);
        return $this;
    }

    public function merchant()
    {
        $ret = parent::merchant();
        $id = $this->order->getId();
        $ret['notify_url'] = $this->urlBuilder->getUrl('sequra/ipn');
        $urL_parameters = [
            'id' => $id,
            'method' => $this->order->getPayment()->getMethod(),
            'signature' => $this->sign($id)
        ];
        $ret['notification_parameters'] = $urL_parameters;
        $ret['return_url'] = $this->urlBuilder->getUrl('sequra/comeback', ['quote_id' => $id]);
        $ret['events_webhook'] = [
            'url' => $this->urlBuilder->getUrl('sequra/webhook'),
            'parameters' => $urL_parameters,
        ];
        if($this->order->getSequraRemoteSale()){
            $ret['store_ref'] = 'WEB_REMOTE';
            $ret['operator_ref'] = $this->order->getSequraOperatorRef();
        } else if($this->getConfigData('allow_remotesales',$this->getStoreId())) {
            $ret['store_ref'] = 'WEB';
            $ret['operator_ref'] = 'WEB';
        }
        return $ret;
    }

    public function cartWithItems()
    {
        $data = [];
        $data['delivery_method'] = $this->getDeliveryMethod();
        $data['gift'] = false;
        $data['currency'] = $this->order->getQuoteCurrencyCode()?$this->order->getQuoteCurrencyCode():'EUR';
        $data['created_at'] = $this->order->getCreatedAt();
        $data['updated_at'] = $this->order->getUpdatedAt();
        $data['cart_ref'] = $this->order->getReservedOrderId();//$this->order->getQuoteId();
        $data['order_total_with_tax'] = self::integerPrice($this->order->getGrandTotal());
        $data['items'] = $this->items();

        return $data;
    }

    public function deliveryAddress()
    {
        $address = $this->order->getShippingAddress();
        if ('' == $address->getFirstname()) {
            $address = $this->order->getBillingAddress();
        }
        $extra = [];
        /*Specific for biebersdorf_customerordercomment extension*/
        if ('' != $this->order->getData('biebersdorf_customerordercomment')) {
            $extra['extra'] = $this->order->getData('biebersdorf_customerordercomment');
        }

        return array_merge($extra, $this->address($address));
    }

    public function invoiceAddress()
    {
        $address = $this->order->getBillingAddress();

        return $this->address($address);
    }

    public function customer()
    {
        $data = parent::customer();
        $data['language_code'] = self::notNull($this->localeResolver->getLocale());
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $data['ip_number'] = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $data['ip_number'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $data['ip_number'] = $_SERVER['REMOTE_ADDR'];
        }
        $data['user_agent'] = $_SERVER["HTTP_USER_AGENT"];
        $data['logged_in'] = (1 == $this->customerSession->isLoggedIn());

        if ($data['logged_in']) {
            $customer = $this->customerSession->getCustomer();
            $data['created_at'] = self::dateOrBlank($customer->getCreatedAt());
            $data['updated_at'] = self::dateOrBlank($customer->getUpdatedAt());
            $data['date_of_birth'] = self::dateOrBlank($customer->getDob());
            $data['previous_orders'] = self::getPreviousOrders($customer->getId());
        }

        return $data;
    }

    public function getPreviousOrders($customerID)
    {
        $order_model = $this->orderFactory->create();
        $orderCollection = $order_model
            ->getCollection()
            ->addFieldToFilter('customer_id', ['eq' => [$customerID]]);
        $orders = [];
        if ($orderCollection) {
            foreach ($orderCollection as $order_row) {
                $order = [];
                $order['amount'] = self::integerPrice($order_row->getData('grand_total'));
                $order['currency'] = $order_row->getData('order_currency_code');
                $order['created_at'] = str_replace(' ', 'T', $order_row->getData('created_at'));
                $orders[] = $order;
            }
        }

        return $orders;
    }

    public function getDiscountInclTax()
    {
        $discount_with_tax = 0;
        foreach ($this->order->getAllItems() as $item) {
            $discount = $item->getDiscountAmount();
            if (!$this->getGlobalConfigData(\Magento\Tax\Model\Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX)) {
                $discount *= ( 1 + $item->getTaxPercent() / 100 );
            }
            $discount_with_tax += self::integerPrice($discount);
        }
        return -1*$discount_with_tax;
    }

    public function getShippingInclTax()
    {
        return $this->order->getShippingAddress()->getShippingInclTax();
    }

    public function getShippingMethod()
    {
        return $this->order->getShippingAddress()->getShippingMethod();
    }

    public function productItem()
    {
        $items = [];
        foreach ($this->order->getAllVisibleItems() as $itemOb) {
            $item = [];
            $item["reference"] = self::notNull($itemOb->getSku());
            $item["name"] = $itemOb->getName() ? self::notNull($itemOb->getName()) : self::notNull($itemOb->getSku());
            $item["downloadable"] = ($itemOb->getIsVirtual() ? true : false);

            $qty = $itemOb->getQty();
            if ((int)$qty == $qty) {
                $item["quantity"] = (int)$qty;
                $item["price_with_tax"] = self::integerPrice(self::notNull($itemOb->getPriceInclTax()));
                $item["total_with_tax"] = self::integerPrice(self::notNull($itemOb->getRowTotalInclTax()));
            } else { //Fake qty and unitary price
                $item["quantity"] = 1;
                $item["price_with_tax"] = $item["total_with_tax"] = self::integerPrice(self::notNull($itemOb->getRowTotalInclTax()));
            }

            $product = $this->productRepository->getById($itemOb->getProductId());
            $items[] = array_merge($item, $this->fillOptionalProductItemFields($product));
        }

        return $items;
    }

    public function getObjWithCustomerData()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->customerSession->getCustomer();
        }
        return $this->order->getBillingAddress();
    }
}
