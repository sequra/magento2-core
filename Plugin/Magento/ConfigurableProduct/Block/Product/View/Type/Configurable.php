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

/**
 * Class Configurable
 *
 * @package Sequra\Core\Plugin\Magento\ConfigurableProduct\Block\Product\View\Type
 */
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
     * @param ConfigurableProduct $subject
     * @param $result
     *
     * @return string
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function afterGetJsonConfig(ConfigurableProduct $subject, $result): string
    {
        $store = $this->storeManager->getStore();
        $generalSettings = StoreContext::doWithStore($store->getId(), function () {
            return $this->getGeneralSettings();
        });

        $jsonResult = json_decode($result, true);
        $jsonResult['skus'] = [];
        $jsonResult['excludedProducts'] = [];

        if ($generalSettings) {
            $jsonResult['excludedProducts'] = $generalSettings->getExcludedProducts();
        }

        foreach ($subject->getAllowProducts() as $simpleProduct) {
            $jsonResult['skus'][$simpleProduct->getId()] = $simpleProduct->getSku();
        }

        return json_encode($jsonResult);
    }

    /**
     * @return GeneralSettings|null
     */
    private function getGeneralSettings(): ?GeneralSettings
    {
        /** @var GeneralSettingsService $service */
        $service = ServiceRegister::getService(GeneralSettingsService::class);

        return $service->getGeneralSettings();
    }
}
