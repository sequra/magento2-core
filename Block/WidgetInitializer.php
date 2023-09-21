<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Block\Cart;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\StoreConfigManagerInterface;
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
     * @var StoreConfigManagerInterface
     */
    private $storeConfigManager;
    /**
     * @var ProductService
     */
    private $productService;

    /**
     * @param WidgetConfigService $widgetConfigService
     * @param Http $request
     * @param ProductRepository $productRepository
     * @param Cart $cart
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param ProductService $productService
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        WidgetConfigService         $widgetConfigService,
        Http                        $request,
        ProductRepository           $productRepository,
        Cart                        $cart,
        StoreConfigManagerInterface $storeConfigManager,
        ProductService              $productService,
        Template\Context            $context,
        array                       $data = []
    )
    {
        parent::__construct($context, $data);

        $this->widgetConfigService = $widgetConfigService;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->cart = $cart;
        $this->storeConfigManager = $storeConfigManager;
        $this->productService = $productService;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getWidgetConfig(): string
    {
        $actionName = $this->request->getFullActionName();

        if (!in_array($actionName, ['catalog_product_view', 'checkout_cart_index', 'catalog_category_view'])) {
            return json_encode([]);
        }

        $config = StoreContext::doWithStore($this->_storeManager->getStore()->getId(), function () use ($actionName) {
            return $this->getConfig($actionName);
        });

        return json_encode(
            [
                '[data-content-type="Sequra_Core"]' => [
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
        $storeConfig = $this->storeConfigManager->getStoreConfigs([$this->_storeManager->getStore()->getCode()])[0];
        $widgetSettings = $this->getWidgetSettings();

        if (empty($widgetSettings)) {
            return [];
        }

        if ($storeConfig->getBaseCurrencyCode() !== 'EUR' ||
            ($actionName === 'catalog_product_view' && !$widgetSettings->isDisplayOnProductPage()
                && !$widgetSettings->isShowInstallmentsInProductListing()) ||
            ($actionName === 'checkout_cart_index' && !$widgetSettings->isShowInstallmentsInCartPage())
            || ($actionName === 'catalog_category_view' && !$widgetSettings->isShowInstallmentsInProductListing())) {
            return [];
        }

        if ($actionName === 'catalog_product_view') {
            $productId = $this->request->getParam('id');
            $product = $this->productRepository->getById($productId);

            if (!$this->isWidgetEnabledForProduct($product)) {
                return [];
            }

            $amount = $product->getFinalPrice() * 100;
        }

        if ($actionName === 'checkout_cart_index') {
            $totals = $this->cart->getTotals();
            $amount = $totals['grand_total']['value'] * 100;
        }

        $config = $this->widgetConfigService->getData($this->_storeManager->getStore()->getId());

        return $config ? array_merge($config, ['amount' => $amount]) : [];
    }

    /**
     * @param Product $product
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    private function isWidgetEnabledForProduct(Product $product): bool
    {
        $settings = $this->getGeneralSettings();

        if (!$settings) {
            return true;
        }

        $categoryIds = $product->getCategoryIds();
        $trail = $this->productService->getAllProductCategories($categoryIds);

        return !in_array($product->getSku(), $settings->getExcludedProducts())
            && empty(array_intersect($trail, $settings->getExcludedCategories()));
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
}
