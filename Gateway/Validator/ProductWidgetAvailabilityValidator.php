<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;

class ProductWidgetAvailabilityValidator extends AbstractWidgetAvailabilityValidator
{

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Http $request
     * @param ProductRepository $productRepository
     * @param ProductService $productService
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Http $request,
        ProductRepository $productRepository,
        ProductService         $productService
    ) {
        parent::__construct($resultFactory, $request);
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    /**
     * @inheritdoc
     */
    protected function getActionNames()
    {
        return ['catalog_product_view'];
    }

    /**
     * Check if the option is enabled in the settings
     *
     * @param WidgetSettings $widgetSettings
     * @return bool
     */
    protected function isEnabledInSettings($widgetSettings): bool
    {
        return $widgetSettings->isDisplayOnProductPage();
    }

    /**
     * @inheritdoc
     */
    protected function getValidationResult(array $validationSubject)
    {
        if (!parent::getValidationResult($validationSubject)) {
            return false;
        }
        if (!isset($validationSubject['productId'])) {
            return false;
        }
        $storeId = (string) $validationSubject['storeId'];
        $widgetSettings = $this->getWidgetSettings($storeId);
        
        if (empty($widgetSettings) || !$this->isEnabledInSettings($widgetSettings)) {
            return false;
        }
        /**
         * @var \Magento\Catalog\Model\Product $product
         */
        $product = $this->productRepository->getById((int) $validationSubject['productId']);
        $settings = $this->getGeneralSettings($storeId);
        return $this->isWidgetEnabledForProduct($product, $settings);
    }

    /**
     * Is widget enabled for the given product
     *
     * @param Product $product
     * @param GeneralSettings|null $settings
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    protected function isWidgetEnabledForProduct(Product $product, ?GeneralSettings $settings): bool
    {

        if ($product->getIsVirtual() || $product->getTypeId() === 'grouped') {
            return false;
        }

        $excludedProducts = $settings ? $settings->getExcludedProducts() : [];
        if (!is_array($excludedProducts)) {
            $excludedProducts = [];
        }

        if (!empty($excludedProducts) &&
            (in_array($product->getData('sku'), $excludedProducts)
            || in_array($product->getSku(), $excludedProducts))
            ) {
            return false;
        }

        $excludedCategories = $settings ? $settings->getExcludedCategories() : [];
        if (!is_array($excludedCategories)) {
            $excludedCategories = [];
        }
        if (empty($excludedCategories)) {
            return true;
        }
        
        $trail = $this->productService->getAllProductCategoryIds($product->getCategoryIds());

        return empty(array_intersect($trail, $excludedCategories));
    }
}
