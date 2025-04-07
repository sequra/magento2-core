<?php

namespace Sequra\Core\Block;

use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Block\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Sequra\Core\Services\BusinessLogic\WidgetConfigService;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Models\SeQuraPaymentMethod;

class WidgetInitializer extends Template
{
     /**
      * @var \Magento\Framework\Locale\ResolverInterface
      */
    protected $localeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $formatter;

    /**
     * @var ConnectionData
     */
    private $connectionSettings;

    /**
     * @var WidgetSettings
     */
    private $widgetSettings;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

     /**
      * @return WidgetSettings|null
      */
    private function getWidgetSettings()
    {
        if (!$this->widgetSettings) {
            try {
                $this->widgetSettings = StoreContext::doWithStore($this->scopeResolver->getScope()->getStoreId(), function () {
                    return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
                });
            } catch (\Throwable $e) {
                // TODO: Log error
            }
        }
        return $this->widgetSettings;
    }

    /**
     * @return ConnectionData|null
     */
    private function getConnectionSettings()
    {
        if (!$this->connectionSettings) {
            try {
                $storeId = $this->scopeResolver->getScope()->getStoreId();
                $this->connectionSettings = StoreContext::doWithStore($storeId, function () {
                    $service = ServiceRegister::getService(ConnectionService::class);
                    return $service->getConnectionData();
                });
            } catch (\Throwable $e) {
                // TODO: Log error
            }
        }
        return $this->connectionSettings;
    }

    /**
     * @param WidgetConfigService $widgetConfigService
     * @param Http $request
     * @param ProductRepository $productRepository
     * @param Cart $cart
     * @param ProductService $productService
     * @param PriceCurrencyInterface $priceCurrency
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Data $catalogHelper
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
        $this->scopeResolver = $scopeResolver;
        $this->formatter = $this->getFormatter();
        $this->session = $checkoutSession;
    }

    private function getFormatter()
    {
        $localeCode = $this->localeResolver->getLocale();
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    public function getDecimalSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    public function getThousandsSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }

    public function getScriptUri()
    {
        $settings = $this->getConnectionSettings();

        return !$settings || !$settings->getEnvironment() ? '' : "https://{$settings->getEnvironment()}.sequracdn.com/assets/sequra-checkout.min.js";
    }

    /**
     * Return the list of payment methods selected in the widget settings
     * Each element is an array with the following:
     * - countryCode
     * - product
     * - campaign
     *
     * @return array<string>
     */
    public function getProducts()
    {
        $paymentMethods = [];
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            // TODO: Log Merchant ID not found
            return $paymentMethods;
        }

        foreach ($this->getPaymentMethods($storeId, $merchantId) as $paymentMethod) {
            // Check if supports widgets
            if (in_array($paymentMethod->getProduct(), ['i1', 'pp5', 'pp3', 'pp6', 'pp9', 'sp1'], true)) {
                $paymentMethods[] = $paymentMethod->getProduct();
            }
        }
        return $paymentMethods;
    }

    /**
     * Get payment methods for a given merchant using the current store context
     *
     * @param string $storeId
     * @param string $merchantId
     *
     * @return SeQuraPaymentMethod[]
     */
    private function getPaymentMethods($storeId, $merchantId)
    {
        $payment_methods = [];
        try {
            $payment_methods = StoreContext::doWithStore($storeId, function () use ($merchantId) {
                return ServiceRegister::getService(PaymentMethodsService::class)->getMerchantsPaymentMethods($merchantId);
            });
        } catch (\Throwable $e) {
            // TODO: Log error
        }
        return $payment_methods;
    }

    public function getAssetsKey()
    {
        $settings = $this->getWidgetSettings();

        return !$settings ? '' : $settings->getAssetsKey();
    }

    private function getCurrentCountry()
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    public function getMerchantId()
    {
        $quote = $this->session->getQuote();
        $shippingCountry = $quote->getShippingAddress()->getCountryId();
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        $data = AdminAPI::get()->countryConfiguration($storeId)->getCountryConfigurations();
        if (!$data->isSuccessful()) {
            return '';
        }
        foreach ($data->toArray() as $country) {
            if ($country['countryCode'] === $shippingCountry && !empty($country['merchantId'])) {
                return $country['merchantId'];
            }
        }

        $currentCountry = $this->getCurrentCountry();
        foreach ($data->toArray() as $country) {
            if ($country['countryCode'] === $currentCountry && !empty($country['merchantId'])) {
                return $country['merchantId'];
            }
        }
        return '';
    }

    public function getLocale()
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }
}
