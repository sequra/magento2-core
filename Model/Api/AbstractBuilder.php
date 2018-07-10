<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api;

use Sequra\Client;

abstract class AbstractBuilder implements BuilderInterface
{
    const STATE_CONFIRMED = 'confirmed';
    const STATE_APPROVED = 'approved';
    public static $centsPerWhole = 100;
    protected $merchant_id;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * Order object or Quote Object
     *
     * @var \Magento\Framework\Model\AbstractModel
     */
    protected $_order;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_productRepository = $productRepository;
        $this->_urlBuilder = $urlBuilder;
        $this->_scopeConfig = $scopeConfig;
        $this->_localeResolver = $localeResolver;
        $this->merchant_id = $this->getConfigData('merchant_ref');
        $this->_logger = $logger;
    }

    public function getConfigData($field, $storeId = null)
    {
        $path = 'sequra/core/' . $field;

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function merchant()
    {
        return array(
            'id' => $this->merchant_id,
        );
    }

    public abstract function deliveryAddress();

    public abstract function invoiceAddress();

    public function items($order)
    {
        return array_merge(
            $this->productItem(),
            $this->extraItems($order),
            $this->handlingItems()
        );
    }

    public abstract function productItem();

    public function extraItems($order)
    {
        $items = array();
        $discount_with_tax = 0;
        //@todo

        foreach ($order->getAllItems() as $item) {
            $dto = $item->getDiscountAmount();
            $discount_with_tax += $dto * 100;
        }

        //order discounts
        if ($discount_with_tax > 0) {
            //$discountExclTax=$discount*1.21; //What kind of tax?
            $item = array();
            $item["type"] = "discount";
            $item["reference"] = self::notNull($order->getCouponCode());
            $item["name"] = 'Descuento';
            $item["total_without_tax"] = $item["total_with_tax"] = -1 * (int)$discount_with_tax;
            $items[] = $item;
        }
        //add Customer fee (without tax)
        if ($order->getSequraSequrapayment() > 0) {
            $item = array();
            $item["type"] = "invoice_fee";
            $item["tax_rate"] = 0;
            $item["total_without_tax"] = $item["total_with_tax"] = self::integerPrice($order->getSequraSequrapayment());

            $items[] = $item;
        }

        return $items;
    }

    public static function notNull($value1)
    {
        return is_null($value1) ? '' : $value1;
    }

    public static function integerPrice($price)
    {
        return intval(round(self::$centsPerWhole * $price));
    }

    public function handlingItems()
    {
        $items = array();
        $deliveryMethod = $this->getDeliveryMethod();

        if (!$deliveryMethod['provider']) {
            return array();
        }

        $incl_tax = $this->getShippingInclTax();

        $handling = array(
            'type' => 'handling',
            'reference' => $deliveryMethod['provider'],
            'name' => $deliveryMethod['name'],
            'tax_rate' => 0,
            'total_without_tax' => self::integerPrice($incl_tax),
            'total_with_tax' => self::integerPrice($incl_tax),
        );

        $items[] = $handling;

        return $items;
    }

    public function getDeliveryMethod()
    {
        $shippingMethod = $this->getShippingMethod();
        $carrier = explode('_', $shippingMethod, 2);
        $title = $this->_scopeConfig->getValue('carriers/' . $carrier[0] . '/title');

        return array(
            'name' => self::notNull(isset($carrier[1]) ? $carrier[1] : 'Envío'),
            'days' => self::notNull($title),
            'provider' => self::notNull($carrier[0]),
        );
    }

    public abstract function getShippingMethod();

    public abstract function getShippingInclTax();

    public function address($address)
    {
        $data = array();
        $data['given_names'] = self::notNull($address->getFirstname());
        $data['surnames'] = self::notNull($address->getLastname());
        $data['company'] = self::notNull($address->getCompany());
        $street = $address->getStreet();
        $data['address_line_1'] = (string)self::notNull($street[0] . (isset($street[1]) ? ", " . $street[1] : ''));
        if (isset($street[2])) {
            $data['address_line_2'] = (string)self::notNull($street[2] . (isset($street[3]) ? ", " . $street[3] : ''));
        } else {
            $data['address_line_2'] = '';
        }
        $data['postal_code'] = self::notNull($address->getPostcode());
        $data['city'] = self::notNull($address->getCity());
        $data['country_code'] = self::notNull($address->getCountryId());
        // OPTIONAL
        $data['state'] = self::notNull($address->getRegion());
        $data['phone'] = self::notNull($address->getTelephone());
        $data['mobile_phone'] = self::notNull($address->getFax());
        $data['vat_number'] = self::notNull($address->getVatId());

        return $data;
    }

    public function customer()
    {
        $customer = $this->getObjWithCustomerData();
        $data = array();
        $data['given_names'] = self::notNull($customer->getFirstname());
        $data['surnames'] = self::notNull($customer->getLastname());
        $data['email'] = self::notNull($customer->getEmail());
        if (!$data['email']) {
            $data['email'] = self::notNull($this->_order->getData('customer_email'));
        }
        // OPTIONAL
        $company = $customer->getCompany();
        if (!is_null($company)) {
            $data['company'] = self::notNull($company);
        }
        $vat = $customer->getVatId();
        if (!is_null($vat)) {
            $data['vat_number'] = self::notNull($vat);
            $data['nin'] = self::notNull($vat);
        }
        $dob = $customer->getCustomerDob();
        if (!is_null($dob)) {
            $data['date_of_birth'] = self::dateOrBlank($dob);
        }
        $data['ref'] = self::notNull($customer->getId());
        if ($title = $customer->getPrefix()) {
            $data['title'] = str_replace(
                array('sra', 'dña', 'srta', 'sr', 'd'),
                array('mrs', 'mrs', 'miss', 'mr', 'mr'),
                strtolower(trim($title, '.'))
            );
        }

        return $data;
    }

    public abstract function getObjWithCustomerData();

    public static function dateOrBlank($date)
    {
        return $date ? date_format(date_create($date), 'Y-m-d') : '';
    }

    public function fillOptionalProductItemFields($product)
    {
        $item = array();
        if (is_object($product)) {
            $item["description"] = self::notNull($product->getDescription());
            $item["product_id"] = self::notNull($product->getId());
            $item["url"] = self::notNull($product->getProductUrl());
//@todo
            /*			$categoryIds         = $product->getCategoryIds();
                        if ( count( $categoryIds ) ) {
                            $firstCategoryId = $categoryIds[0];
                            $_category       = Mage::getModel( 'catalog/category' )->load( $firstCategoryId );
                            if ( $_category->getName() ) {
                                $item["category"] = self::notNull( $_category->getName() );
                            }
                        }
                        if ( $product->getResource()->getAttribute( 'manufacturer' ) ) {
                            if ( $manufacturer = $product->getAttributeText( 'manufacturer' ) ) {
                                $item["manufacturer"] = $manufacturer;
                            }
                        }
            */
        }

        return $item;
    }

    public function gui()
    {
        $data = array(
            'layout' => $this->isMobile() ? 'mobile' : 'desktop',
        );

        return $data;
    }

    public static function isMobile()
    {
        $regex_match = "/(nokia|iphone|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|"
            . "htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|"
            . "blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|"
            . "symbian|smartphone|mmp|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|"
            . "jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220"
            . ")/i";

        if (preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return true;
        }

        if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),
                    'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))
        ) {
            return true;
        }

        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
        $mobile_agents = array(
            'w3c ',
            'acs-',
            'alav',
            'alca',
            'amoi',
            'audi',
            'avan',
            'benq',
            'bird',
            'blac',
            'blaz',
            'brew',
            'cell',
            'cldc',
            'cmd-',
            'dang',
            'doco',
            'eric',
            'hipt',
            'inno',
            'ipaq',
            'java',
            'jigs',
            'kddi',
            'keji',
            'leno',
            'lg-c',
            'lg-d',
            'lg-g',
            'lge-',
            'maui',
            'maxo',
            'midp',
            'mits',
            'mmef',
            'mobi',
            'mot-',
            'moto',
            'mwbp',
            'nec-',
            'newt',
            'noki',
            'oper',
            'palm',
            'pana',
            'pant',
            'phil',
            'play',
            'port',
            'prox',
            'qwap',
            'sage',
            'sams',
            'sany',
            'sch-',
            'sec-',
            'send',
            'seri',
            'sgh-',
            'shar',
            'sie-',
            'siem',
            'smal',
            'smar',
            'sony',
            'sph-',
            'symb',
            't-mo',
            'teli',
            'tim-',
            'tosh',
            'tsm-',
            'upg1',
            'upsi',
            'vk-v',
            'voda',
            'wap-',
            'wapa',
            'wapi',
            'wapp',
            'wapr',
            'webc',
            'winw',
            'winw',
            'xda ',
            'xda-'
        );

        if (in_array($mobile_ua, $mobile_agents)) {
            return true;
        }

        if (isset($_SERVER['ALL_HTTP']) && strpos(strtolower($_SERVER['ALL_HTTP']), 'OperaMini') > 0) {
            return true;
        }

        return false;
    }

    public function platform()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

        $data = array(
            'name' => 'Magento',
            'version' => self::notNull($productMetadata->getVersion()),
            'plugin_version' => '1.0.2',//@todo
            'php_version' => phpversion(),
            'php_os' => PHP_OS,
            'uname' => php_uname(),
            'db_name' => 'mysql',//@todo
            'db_version' => '5.7.x or later'//@todo
        );

        return $data;
    }

    public function sign($value)
    {
        return hash_hmac('sha256', $value, $this->getConfigData('user_secret'));
    }

    protected function fixRoundingProblems($order)
    {
        $totals = \Sequra\PhpClient\Helper::totals($order['cart']);
        $diff_with_tax = $order['cart']['order_total_with_tax'] - $totals['with_tax'];
        $diff_without_tax = $order['cart']['order_total_without_tax'] - $totals['without_tax'];
        /*Don't correct error bigger than 1 cent per line*/
        if (($diff_with_tax == 0 && $diff_without_tax == 0) || count($order['cart']['items']) < abs($diff_with_tax)) {
            return $order;
        }

        $item['type'] = 'discount';
        $item['reference'] = 'Ajuste';
        $item['name'] = 'Ajuste';
        $item['total_without_tax'] = $diff_without_tax;
        $item['total_with_tax'] = $diff_with_tax;
        if ($diff_with_tax > 0) {
            $item['type'] = 'handling';
            $item['tax_rate'] = $diff_without_tax ? round(abs(($diff_with_tax * $diff_without_tax)) - 1) * 100 : 0;
        }
        $order['cart']['items'][] = $item;

        return $order;
    }

}
