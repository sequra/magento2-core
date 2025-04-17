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
        parent::__construct($resultFactory);
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    /**
     * @inheritdoc
     *
     * @param array<string, string|int> $validationSubject
     */
    public function validate(array $validationSubject)
    {
        try {
            if ($this->request->getFullActionName() !== 'catalog_product_view'
            || !isset($validationSubject['storeId'])) {
                return $this->createResult(false);
            }

            $storeId = (string) $validationSubject['storeId'];

            $widgetSettings = $this->getWidgetSettings($storeId);
            $settings = $this->getGeneralSettings($storeId);

            if (empty($widgetSettings) || !$widgetSettings->isEnabled() || !$widgetSettings->isDisplayOnProductPage()) {
                return $this->createResult(false);
            }

            /**
             * @var int $productId
             */
            $productId = $this->request->getParam('id');
            /**
             * @var \Magento\Catalog\Model\Product $product
             */
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
     * Get widget settings for the given store ID
     *
     * @param string $storeId The store ID for which to get widget settings
     *
     * @return WidgetSettings|null
     */
    private function getWidgetSettings($storeId)
    {
        /**
         * @var WidgetSettings|null $settings
         */
        $settings = StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
        });
        return $settings;
    }

    /**
     * Get general settings for the given store ID
     *
     * @param string $storeId The store ID for which to get general settings
     *
     * @return GeneralSettings|null
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function getGeneralSettings($storeId): ?GeneralSettings
    {
        /**
         * @var GeneralSettings|null $settings
         */
        $settings = StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(GeneralSettingsService::class)->getGeneralSettings();
        });
        return $settings;
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
