<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Tree;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class ProductService
{
    /**
     * @var Tree
     */
    private $categoryTree;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var array
     */
    private $resolvedCategories = [];

    /**
     * @param Tree $categoryTree
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        Tree $categoryTree,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->categoryTree = $categoryTree;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Get all product categories
     *
     * @param array $categoryIds
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    public function getAllProductCategories(array $categoryIds): array
    {
        if (!$categoryIds) {
            return [];
        }

        $trails = [];

        foreach ($categoryIds as $categoryId) {
            $trails[] = $this->getTrail($categoryId);
        }

        return array_merge(...$trails);
    }

    /**
     * Get trail of categories for a given category ID
     *
     * @param string $categoryId
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    private function getTrail(string $categoryId): array
    {
        if (isset($this->resolvedCategories[$categoryId])) {
            return $this->resolvedCategories[$categoryId];
        }

        $storeId = StoreContext::getInstance()->getStoreId();
        $category = $this->categoryRepository->get($categoryId, $storeId);
        $categoryTree = $this->categoryTree->setStoreId($storeId)->loadBreadcrumbsArray($category->getPath());

        $categoryTrailArray = [];
        foreach ($categoryTree as $eachCategory) {
            $categoryTrailArray[] = $eachCategory['entity_id'];
        }

        $this->resolvedCategories[$categoryId] = $categoryTrailArray;

        return $categoryTrailArray;
    }
}
