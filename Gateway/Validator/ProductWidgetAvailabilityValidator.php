<?php
namespace Sequra\Core\Gateway\Validator;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class ProductWidgetAvailabilityValidator extends AbstractValidator
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    private $request;

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
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Http $request,
        ProductRepository $productRepository,
        ProductService         $productService
    ) {
        parent::__construct($resultFactory);
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        try {
            if ($this->request->getFullActionName() !== 'catalog_product_view' || !isset($validationSubject['storeId'])) {
                return $this->createResult(false);
            }

            $widgetSettings = $this->getWidgetSettings($validationSubject['storeId']);
            $settings = $this->getGeneralSettings($validationSubject['storeId']);

            if (empty($widgetSettings) || !$widgetSettings->isEnabled() || !$widgetSettings->isDisplayOnProductPage()) {
                return $this->createResult(false);
            }

            $productId = $this->request->getParam('id');
            $product = $this->productRepository->getById($productId);

            if (!$this->isWidgetEnabledForProduct($product, $settings)) {
                return $this->createResult(false);
            }
            return $this->createResult(true);
        } catch (\Throwable $e) {
            return $this->createResult(false);
        }
    }

    /**
     * @return WidgetSettings|null
     */
    private function getWidgetSettings($storeId)
    {
        return StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
        });
    }

    /**
     * @return GeneralSettings|null
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function getGeneralSettings($storeId): ?GeneralSettings
    {
        return StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(GeneralSettingsService::class)->getGeneralSettings();
        });
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
}
