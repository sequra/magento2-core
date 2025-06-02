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
use Magento\Framework\App\Request\Http;

class Teaser extends Template implements BlockInterface
{
    use WidgetTrait;
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
     * @var ProductWidgetAvailabilityValidator
     */
    private $productWidgetAvailabilityValidator;

    /**
     * @var Http
     */
    private $request;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param CurrencyValidator $currencyValidator
     * @param IpAddressValidator $ipAddressValidator
     * @param ProductWidgetAvailabilityValidator $productValidator
     * @param Http $request
     * @param mixed[] $data
     */
    public function __construct(
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\View\Element\Template\Context $context,
        CurrencyValidator $currencyValidator,
        IpAddressValidator $ipAddressValidator,
        ProductWidgetAvailabilityValidator $productValidator,
        Http $request,
        array $data = [],
    ) {
        parent::__construct($context, $data);
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
        $this->productWidgetAvailabilityValidator = $productValidator;
        $this->request = $request;
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
        /**
         * @var int $productId
         */
        $productId = $this->request->getParam('id');
        $subject = [
            'currency' => $store->getCurrentCurrency()->getCode(),
            'storeId' => $store->getId(),
            'productId' => $productId,
        ];

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
     * Prepare the list of available widgets to show in the frontend based on the configuration and the current context
     *
     * @return array
     * @phpstan-return array<int,
     *  array{
     *      product: string,
     *      campaign: string,
     *      priceSel: string,
     *      dest: string,
     *      theme: string|null,
     *      reverse: '0',
     *      minAmount: int|null,
     *      maxAmount: int|null
     *  }>
     */
    public function getAvailableWidgets()
    {
        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            return [];
        }

        $currentCountry = $this->getCurrentCountry();
        /**
         * @var \Magento\Store\Model\Store $store
         */
        $store = $this->scopeResolver->getScope();

        /** @var CachedPaymentMethodsResponse $cachedPaymentMethods */
        $cachedPaymentMethods = CheckoutAPI::get()->cachedPaymentMethods($store->getStoreId())
            ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($merchantId));

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
                || $paymentMethodData['countryCode'] !== $currentCountry
            ) {
                continue;
            }

            foreach ($cachedPaymentMethods->toArray() as $paymentMethod) {
                if (!is_array($paymentMethod) ||
                    !isset($paymentMethod['product']) ||
                    $paymentMethod['product'] !== $paymentMethodData['product'] ||
                    (($paymentMethod['campaign'] ?? null) !== ($paymentMethodData['campaign'] ?? null))
                ) {
                    continue;
                }

                $widgets[] = [
                    'product' => (string) $paymentMethod['product'],
                    'campaign' => (string) $paymentMethod['campaign'],
                    'priceSel' => $priceSelector,
                    'dest' => $destinationSelector,
                    'theme' => $theme,
                    'reverse' => "0",
                    'minAmount' => isset($paymentMethod['minAmount']) ? (int) $paymentMethod['minAmount'] : 0,
                    'maxAmount' => isset($paymentMethod['maxAmount']) ? (int) $paymentMethod['maxAmount'] : null,
                ];

                break;
            }
        }
        return $widgets;
    }
}
