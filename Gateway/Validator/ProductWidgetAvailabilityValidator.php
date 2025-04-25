<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class ProductWidgetAvailabilityValidator extends AbstractWidgetAvailabilityValidator
{

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;

    /**
     * @var ProductService
     */
    private $productService;

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
     * @inheritdoc
     */
    protected function getValidationResult(array $validationSubject)
    {
        if (!parent::getValidationResult($validationSubject)) {
            return false;
        }
        $storeId = (string) $validationSubject['storeId'];
        $widgetSettings = $this->getWidgetSettings($storeId);
        
        if (empty($widgetSettings) || !$widgetSettings->isDisplayOnProductPage()) {
            return false;
        }
        /**
         * @var int $productId
         */
        $productId = $this->request->getParam('id');
        /**
         * @var \Magento\Catalog\Model\Product $product
         */
        $product = $this->productRepository->getById($productId);
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
    private function isWidgetEnabledForProduct(Product $product, ?GeneralSettings $settings): bool
    {

        if ($product->getIsVirtual() || $product->getTypeId() === 'grouped') {
            return false;
        }

        $categoryIds = $product->getCategoryIds();
        $trail = $this->productService->getAllProductCategories($categoryIds);
        $excludedProducts = $settings ? $settings->getExcludedProducts() : [];
        if (!is_array($excludedProducts)) {
            $excludedProducts = [];
        }

        if (in_array($product->getData('sku'), $excludedProducts) || in_array($product->getSku(), $excludedProducts)) {
            return false;
        }

        $excludedCategories = $settings ? $settings->getExcludedCategories() : [];
        if (!is_array($excludedCategories)) {
            $excludedCategories = [];
        }

        return empty(array_intersect($trail, $excludedCategories));
    }
}
