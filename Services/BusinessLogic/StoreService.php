<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Stores\Exceptions\EmptyStoreParameterException;
use SeQura\Core\BusinessLogic\Domain\Stores\Models\Store;

/**
 * Class StoreService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class StoreService implements StoreServiceInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getStoreDomain(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getStores(): array
    {
        $stores = [];
        try {
            $magentoStores = $this->storeManager->getStores();
            foreach ($magentoStores as $store) {
                $stores[] = new Store($store->getId(), $store->getName());
            }
        } catch (EmptyStoreParameterException $e) {
            return [];
        }

        return $stores;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultStore(): ?Store
    {
        $defaultStore = $this->storeManager->getDefaultStoreView();

        try {
            return $defaultStore ? new Store($defaultStore->getId(), $defaultStore->getName()) : null;
        } catch (EmptyStoreParameterException $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getStoreById(string $id): ?Store
    {
        try {
            $store = $this->storeManager->getStore($id);

            return new Store($store->getId(), $store->getName());
        } catch (NoSuchEntityException|EmptyStoreParameterException $e) {
            return null;
        }
    }
}
