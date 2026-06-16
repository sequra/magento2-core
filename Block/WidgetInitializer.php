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
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\Checkout\Requests\CheckoutInitializationRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\Checkout\Responses\CheckoutInitializationResponse;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\PromotionalWidgetsCheckoutResponse;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\Infrastructure\Logger\Logger;

class WidgetInitializer extends Template
{
    /**
     * app/etc/env.php key for the non-production override of the seQura library script URL. Add it
     * in a local/dev environment to point the storefront at a local or ngrok-hosted build of
     * sequra-checkout.min.js, e.g.:
     *
     *     'sequra' => ['dev_assets_script_uri' => 'https://<host>/sequra-assets/sequra-checkout.min.js'],
     *
     * It lives in env.php (not core_config_data) so it is per-environment, never ends up in a DB
     * dump, and needs no system.xml declaration; it is ignored entirely when the app runs in
     * production mode.
     */
    private const DEV_SCRIPT_URI_CONFIG_PATH = 'sequra/dev_assets_script_uri';

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
     * @var State $appState
     */
    private State $appState;

    /**
     * @var DeploymentConfig $deploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

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
     * @param State $appState
     * @param DeploymentConfig $deploymentConfig
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
        State $appState,
        DeploymentConfig $deploymentConfig,
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
        $this->appState = $appState;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * Returns the seQura checkout-library bootstrap config used to load sequra-checkout.min.js.
     *
     * Includes the script URL, merchant identity, locale formatting and supported products.
     * Feature-neutral: it resolves whenever seQura has credentials for the shopper's country,
     * independent of whether promotional widgets are enabled. This is what the storefront uses
     * to inject the library, so every seQura frontend feature (widgets, educational popup,
     * Express Checkout) can rely on it being loaded.
     *
     * @return mixed[]
     */
    public function getInitializationData(): array
    {
        try {
            $quote = $this->session->getQuote();
            $shippingCountry = $quote->getShippingAddress()->getCountryId() ?? '';
            $storeId = (string)$this->_storeManager->getStore()->getId();
            $currentCountry = $this->getCurrentCountry();

            /** @var CheckoutInitializationResponse $initializationData */
            $initializationData = CheckoutAPI::get()
                ->checkout($storeId)
                ->getInitializationData(
                    new CheckoutInitializationRequest($shippingCountry, $currentCountry)
                );

            $data = $initializationData->isSuccessful() ? $initializationData->toArray() : [];

            if (!empty($data)) {
                $override = $this->getDevScriptUriOverride();
                if ($override !== '') {
                    $data['scriptUri'] = $override;
                }
            }

            return $data;
        } catch (Exception $e) {
            Logger::logError('Checkout initialization data failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }

    /**
     * Returns a non-production override for the seQura library script URL, or '' when none applies.
     *
     * Lets a local / ngrok-hosted build of sequra-checkout.min.js be used while testing without
     * touching production: the URL is read from config (self::DEV_SCRIPT_URI_CONFIG_PATH) and is
     * applied only when the application is NOT in production mode, so a deployed production store
     * always loads the real CDN script regardless of the config value.
     *
     * @return string
     *
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getDevScriptUriOverride(): string
    {
        if ($this->appState->getMode() === State::MODE_PRODUCTION) {
            return '';
        }

        $override = $this->deploymentConfig->get(self::DEV_SCRIPT_URI_CONFIG_PATH);

        return is_string($override) ? $override : '';
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
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        $amount = 0;

        if ($actionName === 'catalog_product_view') {
            $idParam = $this->request->getParam('id');
            $productId = is_numeric($idParam) ? (int)$idParam : 0;
            $product = $this->productRepository->getById($productId);

            $amount = $this->getProductPrice($product);
        }

        if ($actionName === 'checkout_cart_index') {
            $totals = $this->cart->getTotals();
            $amount = isset($totals['grand_total']['value'])
                ? (float)$totals['grand_total']['value'] * 100
                : 0.0;
        }

        $config = array_merge(
            $this->getWidgetInitializeData(),
            ['amount' => (int)round($amount), 'action_name' => $actionName]
        );

        $products = $config['products'] ?? [];
        $config['products'] = array_map(fn($value) => ['id' => $value], (array)$products);

        return json_encode(
            [
                '[data-content-type="sequra_core"]' => [
                    'Sequra_Core/js/content-type/sequra-core/appearance/default/widget' => [
                        'widgetConfig' => $config,
                    ]
                ]
            ],
            JSON_THROW_ON_ERROR
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
     * @param ProductInterface $product
     *
     * @return int
     *
     * @throws NoSuchEntityException
     */
    private function getProductPrice(ProductInterface $product): int
    {
        $id = $product->getId();
        if ($id === null) {
            throw new \InvalidArgumentException('Cannot get price: Product ID is null.');
        }

        /** @var Product $productModel */
        $productModel = $product instanceof Product ? $product : $this->productRepository->getById($id);
        $price = (float)$productModel->getFinalPrice();

        if ($productModel->getTypeId() === 'bundle') {
            $regularPrice = $productModel->getPriceInfo()->getPrice('regular_price');
            if ($regularPrice instanceof BundleRegularPrice) {
                $price = (float)$regularPrice->getMinimalPrice()->getValue();
            }
        }

        if ($this->isTaxEnabled() && $productModel->getTypeId() !== 'bundle') {
            $price = (float)$this->catalogHelper->getTaxPrice($productModel, $price, true);
        }

        return (int)round($price * 100);
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
