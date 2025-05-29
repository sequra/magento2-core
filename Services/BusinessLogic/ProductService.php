<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class ProductService
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
     * Gets Magento product by id if type is not "grouped"
     *
     * @param int $productId
     *
     * @return Product|null
     * @throws NoSuchEntityException
     */
    public function getProductById(int $productId): ?Product
    {
        $product = $this->productRepository->getById($productId);
        if ($product->getTypeId() === 'grouped') {
            return null;
        }

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
    public function getAllProductCategoryIds(array $categoryIds): array
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
