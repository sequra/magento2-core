<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\DataAccess\ConnectionData\Entities\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Stores\Exceptions\EmptyStoreParameterException;
use SeQura\Core\BusinessLogic\Domain\Stores\Models\Store;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use SeQura\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;

class StoreService implements StoreServiceInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * StoreService constructor.
     *
     * @param StoreManagerInterface $storeManager
     */
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
                $stores[] = new Store((string) $store->getId(), $store->getName());
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
            return $defaultStore ? new Store((string) $defaultStore->getId(), $defaultStore->getName()) : null;
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

            return new Store((string) $store->getId(), $store->getName());
        } catch (NoSuchEntityException|EmptyStoreParameterException $e) {
            return null;
        }
    }

    /**
     * Retrieves connected store ids.
     *
     * @return array<string>
     *
     * @throws RepositoryNotRegisteredException
     */
    public function getConnectedStores(): array
    {
        $connectionData = $this->getRepository()->select();
        $result = [];

        /** @var ConnectionData $entity */
        foreach ($connectionData as $entity) {
            $result[] = $entity->getStoreId();
        }

        return $result;
    }

    /**
     * Get repository instance.
     *
     * @return RepositoryInterface
     *
     * @throws RepositoryNotRegisteredException
     */
    private function getRepository(): RepositoryInterface
    {
        return RepositoryRegistry::getRepository(ConnectionData::getClassName());
    }
}
