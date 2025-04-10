<?php

namespace Sequra\Core\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
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
     * @var \Magento\Framework\Locale\ResolverInterface
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
                $this->widgetSettings = StoreContext::doWithStore(
                    $this->scopeResolver->getScope()->getStoreId(),
                    function () {
                        return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
                    }
                );

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
                $storeId = $this->scopeResolver->getScope()->getStoreId();
                $this->countrySettings = StoreContext::doWithStore($storeId, function () {
                    $settings = ServiceRegister::getService(CountryConfigurationService::class);
                    return $settings->getCountryConfiguration();
                });
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
                $storeId = $this->scopeResolver->getScope()->getStoreId();
                $this->connectionSettings = StoreContext::doWithStore($storeId, function () {
                    $service = ServiceRegister::getService(ConnectionService::class);
                    return $service->getConnectionData();
                });
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
     * @param array $data
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
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        $subject = ['currency' => $currency->getCode(), 'storeId' => $storeId];

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
        $localeCode = $this->localeResolver->getLocale();
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
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
     * @return array<string, string>
     */
    private function getPaymentMethodsData()
    {
        return array_map(
            function ($value) {
                // TODO: The use of function base64_decode() is discouraged
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                return json_decode(base64_decode($value), true);
            },
            explode(',', $this->getData('payment_methods'))
        );
    }

    /**
     * Get product list from payment methods data.
     *
     * @return array
     */
    public function getProduct()
    {
        $products = [];
        $payment_methods = explode(',', $this->getData('payment_methods'));
        foreach ($payment_methods as $value) {
            // TODO: The use of function base64_decode() is discouraged
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $decoded = json_decode(base64_decode($value), true);
            if (isset($decoded['product'])) {
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
     * @return array
     */
    public function getAvailableWidgets()
    {
        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            return [];
        }

        $currentCountry = $this->getCurrentCountry();
        /** @var CachedPaymentMethodsResponse $cachedPaymentMethods */
        $cachedPaymentMethods = CheckoutAPI::get()->cachedPaymentMethods($this->scopeResolver->getScope()->getStoreId())
            ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($merchantId));
        $priceSelector = addslashes($this->getData('price_sel') ?: '');
        $theme = $this->getData('theme') ?: $this->getWidgetSettings()->getWidgetConfig();
        // TODO: The use of function addslashes() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $destinationSelector = addslashes($this->getData('dest_sel') ?: '');

        $widgets = [];
        foreach ($this->getPaymentMethodsData() as $paymentMethodData) {
            if (!isset($paymentMethodData['countryCode'], $paymentMethodData['product'])
            || $paymentMethodData['countryCode'] !== $currentCountry) {
                continue;
            }

            foreach ($cachedPaymentMethods->toArray() as $paymentMethod) {
                if ($paymentMethod['product'] !== $paymentMethodData['product'] ||
                    $paymentMethod['campaign'] !== $paymentMethodData['campaign']) {
                    continue;
                }

                $widgets[] = [
                    'product' => $paymentMethod['product'],
                    'campaign' => $paymentMethod['campaign'] ?? '',
                    'priceSel' => $priceSelector,
                    'dest' => $destinationSelector,
                    'theme' => $theme,
                    'reverse' => "0",
                    'minAmount' => $paymentMethod['minAmount'] ?? 0,
                    'maxAmount' => $paymentMethod['maxAmount'] ?? null,
                ];

                break;
            }
        }
        return $widgets;
    }
}
