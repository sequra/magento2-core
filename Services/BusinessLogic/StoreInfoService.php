<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\StoreInfo\StoreInfoServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Stores\Models\StoreInfo;

class StoreInfoService implements StoreInfoServiceInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;
    /**
     * @var ModuleListInterface
     */
    private $moduleList;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList,
        ResourceConnection $resourceConnection
    ) {
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return StoreInfo
     *
     * @throws NoSuchEntityException
     */
    public function getStoreInfo(): StoreInfo
    {
        $storeName = $this->storeManager->getStore()->getName();
        $storeUrl = $this->storeManager->getStore()->getBaseUrl();
        $platform = 'Magento';
        $platformVersion = $this->productMetadata->getVersion();
        $module = $this->moduleList->getOne('Sequra_Core');
        $pluginVersion = $module['setup_version'] ?? 'unknown';
        $phpVersion = PHP_VERSION;
        $dbVersion = $this->resourceConnection->getConnection()->fetchOne('SELECT VERSION()');
        $os = PHP_OS_FAMILY;
        $modules = array_keys($this->moduleList->getAll());

        return new StoreInfo($storeName, $storeUrl, $platform, $platformVersion, $pluginVersion, $phpVersion,
            $dbVersion, $os, $modules);
    }
}
