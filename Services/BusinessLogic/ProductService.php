<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Integration\Product\ProductServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Product\Model\ShopProduct;

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
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory; // @phpstan-ignore-line

    /**
     * @param ProductRepository $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     */
    public function __construct(
        ProductRepository  $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionFactory $productCollectionFactory // @phpstan-ignore-line
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productCollectionFactory = $productCollectionFactory;
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
     * @param string $id
     *
     * @return Product|null
     * @throws NoSuchEntityException
     */
    public function getProductById(string $id): ?Product
    {
        if (!is_numeric($id)) {
            return null;
        }

        $productId = (int) $id;

        if (self::$products[$productId] ?? null) {
            return self::$products[$productId];
        }

        /** @var Product $product */
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

    /**
     * Retrieve paginated products with optional name search.
     *
     * @param int $page
     * @param int $limit
     * @param string $search
     *
     * @return array|ShopProduct[]
     */
    public function getShopProducts(int $page, int $limit, string $search): array
    {
        $products = [];

        $this->searchCriteriaBuilder
            ->setPageSize($limit)
            ->setCurrentPage($page);

        if ($search !== '') {
            $this->searchCriteriaBuilder->addFilter(
                'name',
                '%' . $search . '%',
                'like'
            );
        }

        $criteria = $this->searchCriteriaBuilder->create();

        $result = $this->productRepository->getList($criteria);

        foreach ($result->getItems() as $product) {
            $id = $product->getId();
            $name = $product->getName();
            $sku = $product->getSku();

            if ($id === null || $name === null) {
                continue;
            }

            $products[] = new ShopProduct((string)$id, $sku, $name);
        }

        return $products;
    }

    /**
     * Retrieve store products by their IDs.
     *
     * @param string[] $ids
     * @return ShopProduct[]
     */
    public function getShopProductByIds(array $ids): array
    {
        $products = [];

        if (empty($ids)) {
            return $products;
        }

        $storeId = StoreContext::getInstance()->getStoreId();

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->productCollectionFactory->create(); // @phpstan-ignore-line
        $collection->addAttributeToSelect('*');
        $collection->addFieldToFilter('sku', ['in' => $ids]);
        $collection->addStoreFilter($storeId);

        /**
         * @var \Magento\Catalog\Model\Product $product
         */
        foreach ($collection as $product) {
            $id = $product->getId();
            $name = $product->getName();
            $sku = $product->getSku();

            if ($id === null || $name === null) {
                continue;
            }

            $products[] = new ShopProduct((string)$id, $sku, $name);
        }

        return $products;
    }
}
