<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\SellingCountries\SellingCountriesServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class SellingCountriesService implements SellingCountriesServiceInterface
{
    /**
     * @var AllowedCountries
     */
    private $country;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * SellingCountriesService constructor.
     *
     * @param AllowedCountries $country
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(AllowedCountries $country, StoreManagerInterface $storeManager)
    {
        $this->country = $country;
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
        // @phpstan-ignore-next-line
        return $this->country->getAllowedCountries(ScopeInterface::SCOPE_WEBSITES, [$store->getWebsiteId()]);
    }
}
