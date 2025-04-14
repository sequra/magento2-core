<?php
namespace Sequra\Core\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use Sequra\Core\Gateway\Validator\ProductWidgetAvailabilityValidator;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Models\SeQuraPaymentMethod;

class Teaser extends Template implements BlockInterface
{
    /**
     * @var string
     */
    protected static $paymentCode;
    
    /**
     * @var string
     */
    protected $_template = "widget/teaser.phtml";
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \NumberFormatter
     */
    protected $formatter;

    /**
     * @var IpAddressValidator
     */
    private $ipAddressValidator;
    /**
     * @var CurrencyValidator
     */
    private $currencyValidator;

    /**
     * @var ProductWidgetAvailabilityValidator
     */
    private $productWidgetAvailabilityValidator;

    /**
     * @var ConnectionData
     */
    private $connectionSettings;

    /**
     * @var WidgetSettings
     */
    private $widgetSettings;

    /**
     * @var CountryConfiguration[]
     */
    private $countrySettings;

    /**
     * Get widget settings
     *
     * @return WidgetSettings|null
     */
    private function getWidgetSettings()
    {
        if (!$this->widgetSettings) {
            try {
                $storeId = (string) $this->_storeManager->getStore()->getId();
                /**
                 * @var ?WidgetSettings $widgetSettings
                 */
                $widgetSettings = StoreContext::doWithStore($storeId, function () {
                    /**
                     * @var WidgetSettingsService $service
                     */
                    $service = ServiceRegister::getService(WidgetSettingsService::class);
                    return $service->getWidgetSettings();
                });

                if (!$widgetSettings) {
                    return null;
                }

                $this->widgetSettings = $widgetSettings;

                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->widgetSettings;
    }

    /**
     * Get country settings
     *
     * @return CountryConfiguration[]|null
     */
    private function getCountrySettings()
    {
        if (!$this->countrySettings) {
            try {
                $storeId = (string) $this->_storeManager->getStore()->getId();
                /**
                 * @var ?CountryConfiguration[] $countrySettings
                 */
                $countrySettings = StoreContext::doWithStore($storeId, function () {
                    /**
                     * @var CountryConfigurationService $settings
                     */
                    $settings = ServiceRegister::getService(CountryConfigurationService::class);
                    return $settings->getCountryConfiguration();
                });
                if (!$countrySettings) {
                    return null;
                }
                $this->countrySettings = $countrySettings;
                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->countrySettings;
    }

    /**
     * Get connection settings
     *
     * @return ConnectionData|null
     */
    private function getConnectionSettings()
    {
        if (!$this->connectionSettings) {
            try {
                $storeId = (string) $this->_storeManager->getStore()->getId();
                /**
                 * @var ?ConnectionData $connectionSettings
                 */
                $connectionSettings = StoreContext::doWithStore($storeId, function () {
                    /**
                     * @var ConnectionService $service
                     */
                    $service = ServiceRegister::getService(ConnectionService::class);
                    return $service->getConnectionData();
                });
                if (!$connectionSettings) {
                    return null;
                }
                $this->connectionSettings = $connectionSettings;
                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->connectionSettings;
    }

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param CurrencyValidator $currencyValidator
     * @param IpAddressValidator $ipAddressValidator
     * @param ProductWidgetAvailabilityValidator $productValidator
     * @param mixed[] $data
     */
    public function __construct(
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\View\Element\Template\Context $context,
        CurrencyValidator $currencyValidator,
        IpAddressValidator $ipAddressValidator,
        ProductWidgetAvailabilityValidator $productValidator,
        array $data = []
    ) {
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        parent::__construct($context, $data);
        $this->formatter = $this->getFormatter();
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
        $this->productWidgetAvailabilityValidator = $productValidator;
    }
    
    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    
    /**
     * Validate before producing html
     *
     * @return string
     */
    protected function _toHtml()
    {
        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();
        $subject = ['currency' => $store->getCurrentCurrency()->getCode(), 'storeId' => $store->getId()];

        if (!$this->currencyValidator->validate($subject)->isValid()) {
            // TODO: Log currency error
            return '';
        }
        
        if (!$this->ipAddressValidator->validate($subject)->isValid()) {
            // TODO: Log IP error
            return '';
        }

        if (!$this->productWidgetAvailabilityValidator->validate($subject)->isValid()) {
            // TODO: Log product is not eligible for widgets
            return '';
        }
        
        return parent::_toHtml();
    }
    // phpcs:enable

    /**
     * Get formatter instance.
     *
     * @return \NumberFormatter
     */
    private function getFormatter()
    {
        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();
        $currency = $store->getCurrentCurrency();
        $localeCode = $this->localeResolver->getLocale();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    /**
     * Get decimal separator symbol.
     *
     * @return string
     */
    public function getDecimalSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    /**
     * Get thousands separator symbol.
     *
     * @return string
     */
    public function getThousandsSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }

    /**
     * Get script URI for the Sequra CDN.
     *
     * @return string
     */
    public function getScriptUri()
    {
        $settings = $this->getConnectionSettings();
        return !$settings || !$settings->getEnvironment() ?
         '' : "https://{$settings->getEnvironment()}.sequracdn.com/assets/sequra-checkout.min.js";
    }
    
    /**
     * Return the list of payment methods selected in the widget settings
     * Each element is an array with the following:
     * - countryCode
     * - product
     * - campaign
     *
     * @return array<int, mixed>
     */
    private function getPaymentMethodsData()
    {
        /**
         * @var string $data
         */
        $data = $this->getData('payment_methods');
        return array_map(
            function ($value) {
                // TODO: The use of function base64_decode() is discouraged
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                return json_decode(base64_decode($value), true);
            },
            explode(',', $data)
        );
    }

    /**
     * Get product list from payment methods data.
     *
     * @return mixed[]
     */
    public function getProduct()
    {
        $products = [];
        /**
         * @var string $data
         */
        $data = $this->getData('payment_methods');
        $payment_methods = explode(',', $data);
        foreach ($payment_methods as $value) {
            // TODO: The use of function base64_decode() is discouraged
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $decoded = json_decode(base64_decode($value), true);
            if (is_array($decoded) && isset($decoded['product'])) {
                $products[] = $decoded['product'];
            }
        }

        return $products;
    }

    /**
     * Get assets key from widget settings.
     *
     * @return string
     */
    public function getAssetsKey()
    {
        $settings = $this->getWidgetSettings();
        return !$settings ? '' : $settings->getAssetsKey();
    }

    /**
     * Get current country code from locale.
     *
     * @return string
     */
    private function getCurrentCountry()
    {
        $parts = explode('_', $this->localeResolver->getLocale());
        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Get merchant ID for current country.
     *
     * @return string
     */
    private function getMerchantId()
    {
        $country = $this->getCurrentCountry();
        $settingsArr = $this->getCountrySettings();
        if (is_array($settingsArr)) {
            foreach ($settingsArr as $settings) {
                if ($settings->getCountryCode() === $country) {
                    return $settings->getMerchantId();
                }
            }
        }
        return '';
    }

    /**
     * Get formatted locale for widget.
     *
     * @return string
     */
    public function getLocale()
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }

    /**
     * Prepare the list of available widgets to show in the frontend based on the configuration and the current context
     *
     * @return array<int<0, max>, array<string, int|string|null>>
     */
    public function getAvailableWidgets()
    {
        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            return [];
        }

        $currentCountry = $this->getCurrentCountry();
        $paymentMethods = $this->getPaymentMethods($merchantId);
        /**
         * @var string $priceSelData
         */
        $priceSelData = $this->getData('price_sel') ?: '';
        // TODO: The use of function addslashes() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $priceSelector = addslashes($priceSelData);

        /**
         * @var string|null $theme
         */
        $theme = $this->getData('theme');
        if (!isset($theme)) {
            $settings = $this->getWidgetSettings();
            if ($settings) {
                $theme = $settings->getWidgetConfig();
            }
        }

        /**
         * @var string $destSelData
         */
        $destSelData = $this->getData('dest_sel') ?: '';
        // TODO: The use of function addslashes() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $destinationSelector = addslashes($destSelData);

        $widgets = [];
        foreach ($this->getPaymentMethodsData() as $paymentMethodData) {
            if (!is_array($paymentMethodData)
            || !isset($paymentMethodData['countryCode'], $paymentMethodData['product'])
            || $paymentMethodData['countryCode'] !== $currentCountry) {
                continue;
            }

            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->getProduct() !== $paymentMethodData['product']
                || $paymentMethod->getCampaign() !== $paymentMethodData['campaign']) {
                    continue;
                }

                $widgets[] = [
                    'product' => $paymentMethod->getProduct(),
                    'campaign' => $paymentMethod->getCampaign() ?? '',
                    'priceSel' => $priceSelector,
                    'dest' => $destinationSelector,
                    'theme' => $theme,
                    'reverse' => "0",
                    'minAmount' => $paymentMethod->getMinAmount() ?? 0,
                    'maxAmount' => $paymentMethod->getMaxAmount() ?? null,
                ];

                break;
            }
        }
        return $widgets;
    }

    /**
     * Get payment methods for a given merchant using the current store context
     *
     * @param string $merchantId
     *
     * @return SeQuraPaymentMethod[]
     */
    private function getPaymentMethods($merchantId)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $payment_methods = [];
        try {
            /**
             * @var SeQuraPaymentMethod[] $payment_methods
             */
            $payment_methods = StoreContext::doWithStore((string) $storeId, function () use ($merchantId) {
                return ServiceRegister::getService(PaymentMethodsService::class)
                ->getMerchantsPaymentMethods($merchantId);
            });
            // TODO: Log error
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (\Throwable $e) {
        }
        return $payment_methods;
    }
}
