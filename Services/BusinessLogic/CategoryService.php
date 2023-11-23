<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\EmptyCategoryParameterException;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\Category;
use SeQura\Core\BusinessLogic\Domain\Integration\Category\CategoryServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

/**
 * Class CategoryService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class CategoryService implements CategoryServiceInterface
{
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var CategoryCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        CategoryRepository $categoryRepository,
        CategoryCollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     * @throws EmptyCategoryParameterException
     */
    public function getCategories(): array
    {
        $store = $this->storeManager->getStore(StoreContext::getInstance()->getStoreId());
        $categoryCollection = $this->collectionFactory->create();
        $categoryCollection->addAttributeToSelect('*');
        $categoryCollection->addPathsFilter('1/' . $store->getRootCategoryId() . '/');        $categoryCollection->addIsActiveFilter();

        $rootCategory = $this->categoryRepository->get($store->getRootCategoryId());
        $categories[] = new Category($rootCategory->getId(), $rootCategory->getName());
        foreach ($categoryCollection as $category) {
            $categories[] = new Category($category->getId(),$category->getName());
        }

        return $categories;
    }
}
