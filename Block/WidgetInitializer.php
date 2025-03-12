<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Block\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Sequra\Core\Services\BusinessLogic\WidgetConfigService;

/**
 * Class WidgetInitializer
 *
 * @package Sequra\Core\Block
 */
class WidgetInitializer extends Template
{
    /**
     * @var WidgetConfigService
     */
    private $widgetConfigService;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var ProductRepository
     */
    private $productRepository;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var ProductService
     */
    private $productService;
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Data
     */
    private $catalogHelper;

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
        WidgetConfigService    $widgetConfigService,
        Http                   $request,
        ProductRepository      $productRepository,
        Cart                   $cart,
        ProductService         $productService,
        PriceCurrencyInterface $priceCurrency,
        ScopeConfigInterface   $scopeConfig,
        StoreManagerInterface  $storeManager,
        Data                   $catalogHelper,
        Template\Context       $context,
        array                  $data = []
    )
    {
        parent::__construct($context, $data);

        $this->widgetConfigService = $widgetConfigService;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->cart = $cart;
        $this->productService = $productService;
        $this->priceCurrency = $priceCurrency;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getWidgetConfig(): string
    {
        $actionName = $this->request->getFullActionName();

        if (!in_array(
            $actionName,
            ['catalog_product_view', 'checkout_cart_index', 'catalog_category_view', 'cms_index_index', 'catalogsearch_result_index']
        )) {
            return json_encode([]);
        }

        $config = StoreContext::doWithStore($this->_storeManager->getStore()->getId(), function () use ($actionName) {
            return $this->getConfig($actionName);
        });

        return json_encode(
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
     * @param string $actionName
     *
     * @return array
     *
     * @throws Exception
     */
    private function getConfig(string $actionName): array
    {
        $amount = 0;
        $widgetSettings = $this->getWidgetSettings();
        $settings = $this->getGeneralSettings();

        if (empty($widgetSettings) || !$widgetSettings->isEnabled() ||
            $this->shouldNotDisplayWidgets($settings, $widgetSettings, $actionName)) {
            return [];
        }

        if ($actionName === 'catalog_product_view') {
            $productId = $this->request->getParam('id');
            $product = $this->productRepository->getById($productId);

            if (!$this->isWidgetEnabledForProduct($product, $settings)) {
                return [];
            }

            $amount = $this->getProductPrice($product);
        }

        if ($actionName === 'checkout_cart_index') {
            $items = $this->cart->getItems();

            /** @var Item $item */
            foreach ($items as $item) {
                if (!$this->isWidgetEnabledForProduct($item->getProduct(), $settings)) {
                    return [];
                }
            }

            $totals = $this->cart->getTotals();
            $amount = $totals['grand_total']['value'] * 100;
        }

        $config = $this->widgetConfigService->getData($this->_storeManager->getStore()->getId());

        return $config ? array_merge($config, ['amount' => (int)round($amount), 'action_name' => $actionName]) : [];
    }

    /**
     * @param GeneralSettings|null $settings
     * @param WidgetSettings|null $widgetSettings
     * @param string $actionName
     *
     * @return bool
     */
    private function shouldNotDisplayWidgets(
        ?GeneralSettings $settings,
        ?WidgetSettings  $widgetSettings,
        string           $actionName
    ): bool
    {
        return $this->priceCurrency->getCurrency()->getCurrencyCode() !== 'EUR' ||
            ($settings && !empty($settings->getAllowedIPAddresses()) && !empty($ipAddress = $this->getCustomerIpAddress()) &&
                !in_array($ipAddress, $settings->getAllowedIPAddresses(), true))
            || ($actionName === 'catalog_product_view' && !$widgetSettings->isDisplayOnProductPage()
                && !$widgetSettings->isShowInstallmentsInProductListing()) ||
            ($actionName === 'checkout_cart_index' && !$widgetSettings->isShowInstallmentsInCartPage())
            || ($actionName === 'catalog_category_view' && !$widgetSettings->isShowInstallmentsInProductListing())
            || (($actionName === 'cms_index_index' || $actionName === 'catalogsearch_result_index')
                && !$widgetSettings->isShowInstallmentsInProductListing());
    }

    /**
     * @param Product $product
     * @param GeneralSettings|null $settings
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    private function isWidgetEnabledForProduct(Product $product, ?GeneralSettings $settings): bool
    {
        $categoryIds = $product->getCategoryIds();
        $trail = $this->productService->getAllProductCategories($categoryIds);

        return !in_array($product->getData('sku'), $settings ? $settings->getExcludedProducts() : [])
            && !in_array($product->getSku(), $settings ? $settings->getExcludedProducts() : [])
            && empty(array_intersect($trail, $settings ? $settings->getExcludedCategories() : []))
            && !$product->getIsVirtual() && $product->getTypeId() !== 'grouped';
    }

    /**
     * @return GeneralSettings|null
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function getGeneralSettings(): ?GeneralSettings
    {
        $settingsService = ServiceRegister::getService(GeneralSettingsService::class);

        return $settingsService->getGeneralSettings();
    }

    /**
     * @return WidgetSettings|null
     */
    private function getWidgetSettings(): ?WidgetSettings
    {
        $widgetService = ServiceRegister::getService(WidgetSettingsService::class);

        return $widgetService->getWidgetSettings();
    }

    /**
     * @param Product $product
     * @return float|int
     *
     * @throws NoSuchEntityException
     */
    private function getProductPrice(Product $product)
    {
        $price = ($product->getTypeId() === 'bundle' ?
            $product->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue() :
            $product->getFinalPrice());

        if ($product->getTypeId() === 'bundle' || !$this->isTaxEnabled()) {
            return $price * 100;
        }

        return $this->catalogHelper->getTaxPrice($product, $price, true) * 100;
    }

    /**
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
        $taxSettings = $this->scopeConfig->getValue('tax/display/type', ScopeInterface::SCOPE_WEBSITES, $store->getWebsiteId());

        if ($taxSettings) {
            return $taxSettings > 1;
        }

        $taxSettings = $this->scopeConfig->getValue('tax/display/type');

        return $taxSettings > 1;
    }

    private function getCustomerIpAddress(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }
}
