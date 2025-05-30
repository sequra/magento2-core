<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Integration\Product\ProductServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class ProductService implements ProductServiceInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var array<string, array<string>>
     */
    private $resolvedCategories = [];
    /**
     * @var array<int, Product>
     */
    private static $products = [];

    /**
     * @param ProductRepository $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        ProductRepository  $productRepository,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @inheritDoc
     *
     * @param string $productId
     *
     * @return ?string
     *
     * @throws NoSuchEntityException
     */
    public function getProductsSkuByProductId(string $productId): ?string
    {
        $product = $this->getProductById($productId);

        if (!$product) {
            return null;
        }

        return $product->getSku() ?? '';
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     */
    public function isProductVirtual(string $productId): bool
    {
        $product = $this->getProductById($productId);

        if (!$product) {
            return false;
        }

        return $product->getIsVirtual() ?? false;
    }

    /**
     * @inheritDoc
     *
     * @param string $productId
     *
     * @return string[]
     *
     * @throws NoSuchEntityException
     */
    public function getProductCategoriesByProductId(string $productId): array
    {
        $product = $this->getProductById($productId);

        if (!$product) {
            return [];
        }

        return $this->getAllProductCategoryIds($product->getCategoryIds());
    }

    /**
     * Gets Magento product by id if type is not "grouped"
     *
     * @param int $productId
     *
     * @return Product|null
     * @throws NoSuchEntityException
     */
    public function getProductById(int $productId): ?Product
    {
        if (self::$products[$productId] ?? null) {
            return self::$products[$productId];
        }

        $product = $this->productRepository->getById($productId);
        if ($product->getTypeId() === 'grouped') {
            return null;
        }

        self::$products[$productId] = $product;

        return $product;
    }

    /**
     * Get all product categories
     *
     * @param array<string> $categoryIds
     *
     * @return array<string>
     *
     * @throws NoSuchEntityException
     */
    private function getAllProductCategoryIds(array $categoryIds): array
    {
        if (!$categoryIds) {
            return [];
        }

        $trails = [];

        foreach ($categoryIds as $categoryId) {
            $trails[] = $this->getTrail($categoryId);
        }

        return array_unique(array_merge(...$trails));
    }

    /**
     * Get trail of categories for a given category ID
     *
     * @param string $categoryId
     *
     * @return array<string>
     *
     * @throws NoSuchEntityException
     */
    private function getTrail(string $categoryId): array
    {
        if (isset($this->resolvedCategories[$categoryId])) {
            return $this->resolvedCategories[$categoryId];
        }

        $storeId = (int) StoreContext::getInstance()->getStoreId();
        $category = $this->categoryRepository->get((int) $categoryId, $storeId);
        $categories = explode('/', $category->getPath() ?? '');
        if (count($categories) < 2) {
            return [];
        }
        unset($categories[0]);
        return $categories;
    }
}
