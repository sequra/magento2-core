<?php

namespace Sequra\Core\Plugin\Magento\ConfigurableProduct\Block\Product\View\Type;

use Exception;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableProduct;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\Infrastructure\ServiceRegister;

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
     * @throws Exception
     */
    public function afterGetJsonConfig(ConfigurableProduct $subject, $result): string
    {
        $store = $this->storeManager->getStore();
        /**
         * @var GeneralSettings|null $generalSettings
         */
        $generalSettings = StoreContext::doWithStore((string) $store->getId(), function () {
            return $this->getGeneralSettings();
        });

        $jsonResult = json_decode($result, true);
        if (!is_array($jsonResult)) {
            $jsonResult = [];
        }
        $jsonResult['skus'] = [];
        $jsonResult['excludedProducts'] = [];

        if ($generalSettings) {
            $jsonResult['excludedProducts'] = $generalSettings->getExcludedProducts();
        }

        foreach ($subject->getAllowProducts() as $simpleProduct) {
            $jsonResult['skus'][$simpleProduct->getId()] = $simpleProduct->getSku();
        }

        return (string) json_encode($jsonResult);
    }

    /**
     * Get the general settings
     *
     * @return GeneralSettings|null
     */
    private function getGeneralSettings(): ?GeneralSettings
    {
        /** @var GeneralSettingsService $service */
        $service = ServiceRegister::getService(GeneralSettingsService::class);

        return $service->getGeneralSettings();
    }
}
