<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Bundle\Pricing\Price\BundleRegularPrice;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Block\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\PromotionalWidgetsCheckoutResponse;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\Infrastructure\Logger\Logger;

class WidgetInitializer extends Template
{
    /**
     * @var ResolverInterface $localeResolver
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var Session $session
     */
    private Session $session;

    /**
     * @var ProductRepository $productRepository
     */
    private ProductRepository $productRepository;

    /**
     * @var Http $request
     */
    private Http $request;

    /**
     * @var Cart $cart
     */
    private Cart $cart;

    /**
     * @var Data $catalogHelper
     */
    private Data $catalogHelper;

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private StoreManagerInterface $storeManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param ResolverInterface $localeResolver
     * @param Session $session
     * @param Http $request
     * @param ProductRepository $productRepository
     * @param Cart $cart
     * @param Data $catalogHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        ResolverInterface $localeResolver,
        Session $session,
        Http $request,
        ProductRepository $productRepository,
        Cart $cart,
        Data $catalogHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
        $this->session = $session;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->cart = $cart;
        $this->catalogHelper = $catalogHelper;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Returns data for widget initialization
     *
     * @return mixed[]
     */
    public function getWidgetInitializeData(): array
    {
        try {
            $quote = $this->session->getQuote();
            $shippingCountry = $quote->getShippingAddress()->getCountryId() ?? '';
            $storeId = (string)$this->_storeManager->getStore()->getId();
            $currentCountry = $this->getCurrentCountry();

            /** @var PromotionalWidgetsCheckoutResponse $widgetInitializeData */
            $widgetInitializeData = CheckoutAPI::get()
                ->promotionalWidgets($storeId)
                ->getPromotionalWidgetInitializeData(
                    new PromotionalWidgetsCheckoutRequest($shippingCountry, $currentCountry)
                );

            return $widgetInitializeData->isSuccessful() ? $widgetInitializeData->toArray() : [];
        } catch (Exception $e) {
            Logger::logError('Widget data initialization failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }

    /**
     * Used for Hyva theme compatibility.
     *
     * @return string
     *
     * @throws Exception
     */
    public function getWidgetConfig(): string
    {
        $actionName = $this->request->getFullActionName();

        if (!in_array(
            $actionName,
            [
                'catalog_product_view',
                'checkout_cart_index',
                'catalog_category_view',
                'cms_index_index',
                'catalogsearch_result_index'
            ]
        )) {
            return json_encode([]);
        }

        $amount = 0;

        if ($actionName === 'catalog_product_view') {
            $productId = (int)$this->request->getParam('id');
            $product = $this->productRepository->getById($productId);

            $amount = $this->getProductPrice($product);
        }

        if ($actionName === 'checkout_cart_index') {
            $totals = $this->cart->getTotals();
            $amount = $totals['grand_total']['value'] * 100;
        }

        $config = array_merge(
            $this->getWidgetInitializeData(),
            ['amount' => (int)round($amount), 'action_name' => $actionName]
        );

        $config['products'] = array_map(fn($value) => ['id' => $value], (array)$config['products'] ?? []);

        return (string)json_encode(
            [
                '[data-content-type="sequra_core"]' => [
                    'Sequra_Core/js/content-type/sequra-core/appearance/default/widget' => [
                        'widgetConfig' => $config,
                    ]
                ]
            ]
        );
    }

    /**
     * Get current country code
     *
     * @return string
     */
    private function getCurrentCountry(): string
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Returns the product price in cents.
     *
     * @param Product $product
     *
     * @return int
     *
     * @throws NoSuchEntityException
     */
    private function getProductPrice(ProductInterface $product): int
    {
        $price = $product->getFinalPrice();

        if ($product->getTypeId() === 'bundle') {
            $regularPrice = $product->getPriceInfo()->getPrice('regular_price');
            if ($regularPrice instanceof BundleRegularPrice) {
                $price = $regularPrice->getMinimalPrice()->getValue();
            }
        }

        if ($this->isTaxEnabled() && $product->getTypeId() !== 'bundle') {
            $price = $this->catalogHelper->getTaxPrice($product, $price, true);
        }

        return (int) round($price * 100);
    }

    /**
     * Returns whether tax is enabled for the current store.
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    private function isTaxEnabled(): bool
    {
        $storeId = StoreContext::getInstance()->getStoreId();
        $taxSettings = $this->scopeConfig->getValue('tax/display/type', ScopeInterface::SCOPE_STORES, $storeId);

        if ($taxSettings) {
            return $taxSettings > 1;
        }

        $store = $this->storeManager->getStore($storeId);
        $taxSettings = $this->scopeConfig->getValue(
            'tax/display/type',
            ScopeInterface::SCOPE_WEBSITES,
            $store->getWebsiteId()
        );

        if ($taxSettings) {
            return $taxSettings > 1;
        }

        $taxSettings = $this->scopeConfig->getValue('tax/display/type');

        return $taxSettings > 1;
    }
}
