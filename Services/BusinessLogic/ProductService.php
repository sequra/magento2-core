<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Tree;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class ProductService
{
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var array<string, array<string>>
     */
    private $resolvedCategories = [];

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
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
