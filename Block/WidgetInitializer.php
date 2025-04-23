<?php

namespace Sequra\Core\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;

class WidgetInitializer extends Template
{
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \NumberFormatter
     */
    protected $formatter;

    /**
     * @var ConnectionData|null
     */
    private $connectionSettings;

    /**
     * @var WidgetSettings|null
     */
    private $widgetSettings;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * Get the widget settings
     *
     * @return WidgetSettings|null
     */
    private function getWidgetSettings()
    {
        if (!$this->widgetSettings) {
            try {
                /**
                 * @var WidgetSettings|null $widgetSettings
                 */
                $widgetSettings = StoreContext::doWithStore(
                    (string) $this->_storeManager->getStore()->getId(),
                    function () {
                        return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
                    }
                );
                $this->widgetSettings = $widgetSettings;
                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->widgetSettings;
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
                $storeId = $this->_storeManager->getStore()->getId();
                /**
                 * @var ConnectionData|null $connectionSettings
                 */
                $connectionSettings = StoreContext::doWithStore((string) $storeId, function () {
                    $service = ServiceRegister::getService(ConnectionService::class);
                    return $service->getConnectionData();
                });
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
     * @param Context $context
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param Session $checkoutSession
     * @param array $data
     * @phpstan-param array<string, mixed> $data
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

    /**
     * Get formatter for currency
     *
     * @return \NumberFormatter
     */
    private function getFormatter()
    {
        $localeCode = $this->localeResolver->getLocale();
        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();
        $currency = $store->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    /**
     * Get decimal separator
     *
     * @return string
     */
    public function getDecimalSeparator()
    {
        return (string) $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    /**
     * Get thousands separator
     *
     * @return string
     */
    public function getThousandsSeparator()
    {
        return (string) $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }

    /**
     * Get the script URI for the widget
     *
     * @return string
     */
    public function getScriptUri()
    {
        $settings = $this->getConnectionSettings();
        if (!$settings || !$settings->getEnvironment()) {
            return '';
        }
        return "https://{$settings->getEnvironment()}.sequracdn.com/assets/sequra-checkout.min.js";
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
        $storeId = (string) $this->_storeManager->getStore()->getId();
        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            Logger::logInfo('Merchant id not found for storeId: ' . $storeId . ' when fetching products');

            return $paymentMethods;
        }
        /** @var CachedPaymentMethodsResponse $cachedPaymentMethods */
        $cachedPaymentMethods = CheckoutAPI::get()->cachedPaymentMethods($storeId)
            ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($merchantId));

        foreach ($cachedPaymentMethods->toArray() as $paymentMethod) {
            if (!is_array($paymentMethod) ||
            !isset($paymentMethod['product']) ||
            !in_array($paymentMethod['product'], ['i1', 'pp5', 'pp3', 'pp6', 'pp9', 'sp1'], true)) {
                continue;
            }
            $paymentMethods[] = $paymentMethod['product'];
        }

        return $paymentMethods;
    }

    /**
     * Gets assets key from widget settings.
     *
     * @return string
     */
    public function getAssetsKey(): string
    {
        $settings = $this->getWidgetSettings();

        return !$settings ? '' : $settings->getAssetsKey();
    }

    /**
     * Get current country code
     *
     * @return string
     */
    private function getCurrentCountry()
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Get the merchant ID for the current store
     *
     * @return string
     */
    public function getMerchantId()
    {
        $quote = $this->session->getQuote();
        $shippingCountry = $quote->getShippingAddress()->getCountryId();
        $storeId = (string) $this->_storeManager->getStore()->getId();
        // @phpstan-ignore-next-line
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

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale()
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }
}
