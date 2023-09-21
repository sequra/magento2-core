<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\SellingCountries\SellingCountriesServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

/**
 * Class SellingCountriesService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class SellingCountriesService implements SellingCountriesServiceInterface
{
    /**
     * @var CountryCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(CountryCollectionFactory $collectionFactory, StoreManagerInterface $storeManager)
    {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     */
    public function getSellingCountries(): array
    {
        $store = $this->storeManager->getStore(StoreContext::getInstance()->getStoreId());
        $countryCollection = $this->collectionFactory->create();
        $countryCollection->addFieldToFilter('country_id', ['in' => $store->getConfig('general/country/allow')] );

        $configuredCountries = [];
        foreach ($countryCollection as $country) {
            $configuredCountries[] = $country->getCountryId();
        }

        return $configuredCountries;
    }
}
