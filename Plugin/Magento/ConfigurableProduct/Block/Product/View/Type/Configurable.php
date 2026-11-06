<?php

namespace Sequra\Core\Plugin\Magento\ConfigurableProduct\Block\Product\View\Type;

use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableProduct;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\GeneralSettings\GeneralSettingsController;

class Configurable
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Encodes the JSON configuration for the configurable product
     *
     * @param ConfigurableProduct $subject
     * @param string $result
     *
     * @return string
     *
     * @throws NoSuchEntityException
     */
    public function afterGetJsonConfig(ConfigurableProduct $subject, $result): string
    {
        $jsonResult = json_decode($result, true);
        if (!is_array($jsonResult)) {
            $jsonResult = [];
        }
        $jsonResult['skus'] = [];
        $jsonResult['excludedProducts'] = $this->getExcludedProducts((string) $this->storeManager->getStore()->getId());

        foreach ($subject->getAllowProducts() as $simpleProduct) {
            $jsonResult['skus'][$simpleProduct->getId()] = $simpleProduct->getSku();
        }

        return (string) json_encode($jsonResult);
    }

    /**
     * Returns the SKUs excluded from seQura in the store, or none when the settings cannot be read.
     *
     * @param string $storeId
     *
     * @return mixed[]
     */
    private function getExcludedProducts(string $storeId): array
    {
        /** @var GeneralSettingsController $controller */
        $controller = AdminAPI::get()->generalSettings($storeId);
        $response = $controller->getGeneralSettings();
        if (!$response->isSuccessful()) {
            return [];
        }

        $excludedProducts = $response->toArray()['excludedProducts'] ?? [];

        return is_array($excludedProducts) ? $excludedProducts : [];
    }
}
