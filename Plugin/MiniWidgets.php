<?php

namespace Sequra\Core\Plugin;

use Exception;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\Render\Amount;
use Magento\Store\Api\Data\StoreConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Sequra\Core\Block\Widget\WidgetTrait;
use Magento\Framework\Escaper;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use Sequra\Core\Gateway\Validator\ProductListingWidgetAvailabilityValidator;
use Magento\Framework\Locale\ResolverInterface;

class MiniWidgets
{
    use WidgetTrait;

    /**
     * @var StoreManagerInterface
     */
    private $_storeManager;
    /**
     * @var StoreConfigManagerInterface
     */
    private $storeConfigManager;
    /**
     * Required by the Trait
     *
     * @var ProductRepository
     * @phpstan-ignore-next-line
     */
    private $productRepository;
    /**
     * Required by the Trait
     *
     * @var ProductService
     * @phpstan-ignore-next-line
     */
    private $productService;
  
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var \Magento\Framework\Escaper
     */
    private $htmlEscaper;

    /**
     * @var ProductListingWidgetAvailabilityValidator
     */
    private $productAvailabilityValidator;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param ProductRepository $productRepository
     * @param ProductService $productService
     * @param PriceCurrencyInterface $priceCurrency
     * @param Escaper $htmlEscaper
     * @param CurrencyValidator $currencyValidator
     * @param IpAddressValidator $ipAddressValidator
     * @param ProductListingWidgetAvailabilityValidator $productValidator
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        StoreManagerInterface       $storeManager,
        StoreConfigManagerInterface $storeConfigManager,
        ProductRepository           $productRepository,
        ProductService              $productService,
        PriceCurrencyInterface      $priceCurrency,
        Escaper $htmlEscaper,
        CurrencyValidator $currencyValidator,
        IpAddressValidator $ipAddressValidator,
        ProductListingWidgetAvailabilityValidator $productValidator,
        ResolverInterface $localeResolver
    ) {
        $this->_storeManager = $storeManager;
        $this->storeConfigManager = $storeConfigManager;
        $this->productRepository = $productRepository;
        $this->productService = $productService;
        $this->priceCurrency = $priceCurrency;
        $this->localeResolver = $localeResolver;
        $this->htmlEscaper = $htmlEscaper;
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
        $this->productAvailabilityValidator = $productValidator;
    }

    /**
     * Runs after the toHtml method
     *
     * @param Amount $amount
     * @param string $result
     *
     * @return string
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function afterToHtml(Amount $amount, $result): string
    {
        if ($amount->getData('zone') !== 'item_list' || $amount->getData('price_type') !== 'finalPrice') {
            return $result;
        }
        // TODO: Call to an undefined method Magento\Framework\Pricing\Price\PriceInterface::getProduct()
        // @phpstan-ignore-next-line
        $product = $amount->getPrice()->getProduct();
        // @phpstan-ignore-next-line
        $currencyCode = $this->priceCurrency->getCurrency()->getCurrencyCode();
        $subject = [
            'currency' => $currencyCode,
            'storeId' => $this->_storeManager->getStore()->getId(),
            'productId' => (int) $product->getId(),
        ];

        if (!$this->currencyValidator->validate($subject)->isValid()) {
            // TODO: Log currency error
            return $result;
        }

        if (!$this->ipAddressValidator->validate($subject)->isValid()) {
            // TODO: Log IP error
            return $result;
        }

        if (!$this->productAvailabilityValidator->validate($subject)->isValid()) {
            // TODO: Log product is not eligible for widgets
            return '';
        }

        $store = $this->_storeManager->getStore();
        $cents = (int) round($amount->getPrice()->getAmount()->getValue() * 100);
        $result .= StoreContext::doWithStore((string) $store->getId(), function () use ($cents, $store) {
            return $this->getHtml($cents, $store);
        });

        return $result;
    }

    /**
     * Get the HTML
     *
     * @param int $amount
     * @param StoreInterface $store
     *
     * @return string
     *
     * @throws HttpRequestException
     * @throws Exception
     */
    private function getHtml(int $amount, StoreInterface $store): string
    {
        $result = '';
        $merchantId = $this->getMerchantId();
        if (!$merchantId) {
            return $result;
        }
        /** @var CachedPaymentMethodsResponse $paymentMethods */
        $paymentMethods = CheckoutAPI::get()->cachedPaymentMethods($this->_storeManager->getStore()->getId())
            ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($merchantId));

        if (!$paymentMethods->isSuccessful()) {
            return $result;
        }

        $widgetConfig = $this->getWidgetSettings();
        if (!$widgetConfig) {
            return $result;
        }
        
        $storeConfig = $this->storeConfigManager->getStoreConfigs([$store->getCode()])[0];

        foreach ($paymentMethods->toArray() as $paymentMethod) {
            if (!is_array($paymentMethod) || $paymentMethod['product'] !== 'pp3') {
                continue;
            }

            $minAmount = (int)($paymentMethod['minAmount'] ?? 0);
            if ($amount < $minAmount) {
                continue;
            }
            $maxAmount = isset($paymentMethod['maxAmount']) ? (int)$paymentMethod['maxAmount']: null;
            if (null !== $maxAmount && $maxAmount < $amount) {
                continue;
            }

            $result .= $this->getWidgetHtml(
                $widgetConfig,
                $storeConfig,
                $paymentMethod['product'] ?? '',
                $paymentMethod['minAmount'] ?? 0,
                $amount
            );
        }

        return $result;
    }

    /**
     * Get the widget HTML
     *
     * @param WidgetSettings $widgetConfig
     * @param StoreConfigInterface $storeConfig
     * @param string $product
     * @param int $minAmount
     * @param int $amount
     *
     * @return string
     */
    private function getWidgetHtml(
        WidgetSettings $widgetConfig,
        StoreConfigInterface $storeConfig,
        string $product,
        int $minAmount,
        int $amount
    ): string {

        $widgetLabels = $widgetConfig->getWidgetLabels();
        if (!$widgetLabels) {
            return '';
        }

        $message = $widgetLabels->getMessages()[$storeConfig->getLocale()] ?? '';
        $belowLimit = $widgetLabels->getMessagesBelowLimit()[$storeConfig->getLocale()] ?? '';

        $dataset = [
            'content-type' => 'sequra_core',
            'amount' => $amount,
            'product' => $product,
            'min-amount' => $minAmount,
            'label' => $message,
            'below-limit' => $belowLimit,
        ];

        $dataset = array_map(
            /**
             * @param string $key
             * @param string $value
             */
            function ($key, $value) {
                /**
                 * @var string $escapedValue
                 */
                $escapedValue = $this->htmlEscaper->escapeHtml((string) $value);
                return sprintf('data-%s="%s"', $key, $escapedValue);
            },
            array_keys($dataset),
            $dataset
        );
        $dataset = implode(' ', $dataset);

        return "<div class=\"sequra-educational-popup sequra-promotion-miniwidget\" $dataset></div>";
    }
}
