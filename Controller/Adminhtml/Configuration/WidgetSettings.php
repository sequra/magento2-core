<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\PromotionalWidgets\Requests\WidgetSettingsRequest;

class WidgetSettings extends BaseConfigurationController
{
    /**
     * @var StoreConfigManagerInterface
     */
    private $storeConfigManager;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * WidgetSettings constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param StoreConfigManagerInterface $storeConfigManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        StoreConfigManagerInterface $storeConfigManager,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context, $jsonFactory);
        $this->storeConfigManager = $storeConfigManager;
        $this->storeManager = $storeManager;

        $this->allowedActions = ['getWidgetSettings', 'setWidgetSettings'];
    }

    /**
     * Retrieves widget settings.
     *
     * @return Json
     *
     * @throws Exception
     */
    protected function getWidgetSettings(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->widgetConfiguration($this->storeId)->getWidgetSettings();

        $result = $data->toArray();

        if (empty($result)) {
            return $this->result->setData($result);
        }

        $result['widgetLabels']['message'] = !empty($result['widgetLabels']['messages']) ?
            reset($result['widgetLabels']['messages']): '';
        $result['widgetLabels']['messageBelowLimit'] = !empty($result['widgetLabels']['messagesBelowLimit']) ?
            reset($result['widgetLabels']['messagesBelowLimit']): '';
        $result['widgetStyles'] = $result['widgetConfiguration'];
        unset($result['widgetLabels']['messages'], $result['widgetLabels']['messagesBelowLimit']);
        unset($result['widgetConfiguration']);

        $this->addResponseCode($data);

        return $this->result->setData($result);
    }

    /**
     * Saves widget settings.
     *
     * @return Json
     *
     * @throws Exception
     */
    protected function setWidgetSettings(): Json
    {
        /**
         * @var array<string, string|array<string, string>|bool|null> $data
         */
        $data = $this->getSequraPostData();
        $store = $this->storeManager->getStore($this->storeId);
        $storeConfig = $this->storeConfigManager->getStoreConfigs([$store->getCode()])[0];

        /**
         * @var bool $useWidgets
         */
        $useWidgets = $data['useWidgets'] ?? false;
        /**
         * @var string|null $assetsKey
         */
        $assetsKey = isset($data['assetsKey']) && is_string($data['assetsKey']) ? $data['assetsKey'] : null;
        /**
         * @var bool $displayWidgetOnProductPage
         */
        $displayWidgetOnProductPage = $data['displayWidgetOnProductPage'] ?? false;
        /**
         * @var bool $showInstallmentAmountInProductListing
         */
        $showInstallmentAmountInProductListing = $data['showInstallmentAmountInProductListing'] ?? false;
        /**
         * @var bool $showInstallmentAmountInCartPage
         */
        $showInstallmentAmountInCartPage = $data['showInstallmentAmountInCartPage'] ?? false;
        /**
         * @var string $miniWidgetSelector
         */
        $miniWidgetSelector = $data['miniWidgetSelector'] ?? '';
        /**
         * @var string $widgetStyles
         */
        $widgetStyles = $data['widgetStyles'] ?? '';
        /**
         * @var string $productPriceSelector
         */
        $productPriceSelector = $data['productPriceSelector'] ?? '';
        /**
         * @var string $defaultProductLocationSelector
         */
        $defaultProductLocationSelector = $data['defaultProductLocationSelector'] ?? '';
        /**
         * @var string $altProductPriceSelector
         */
        $altProductPriceSelector = $data['altProductPriceSelector'] ?? '';
        /**
         * @var string $altProductPriceTriggerSelector
         */
        $altProductPriceTriggerSelector = $data['altProductPriceTriggerSelector'] ?? '';
        /**
         * @var string $cartPriceSelector
         */
        $cartPriceSelector = $data['cartPriceSelector'] ?? '';
        /**
         * @var string $cartLocationSelector
         */
        $cartLocationSelector = $data['cartLocationSelector'] ?? '';
        /**
         * @var string $widgetOnCartPage
         */
        $widgetOnCartPage = $data['widgetOnCartPage'] ?? '';
        /**
         * @var string $listingPriceSelector
         */
        $listingPriceSelector = $data['listingPriceSelector'] ?? '';
        /**
         * @var string $listingLocationSelector
         */
        $listingLocationSelector = $data['listingLocationSelector'] ?? '';
        /**
         * @var string $widgetOnListingPage
         */
        $widgetOnListingPage = $data['widgetOnListingPage'] ?? '';
        /**
         * @var mixed[] $customLocations
         */
        $customLocations = $data['customLocations'] ?? [];

        /**
         * @var array<string, string> $labels
         */
        $labels = $data['widgetLabels'] ?? [];
        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->widgetConfiguration($this->storeId)->setWidgetSettings(
            new WidgetSettingsRequest(
                $useWidgets,
                $assetsKey,
                $displayWidgetOnProductPage,
                $showInstallmentAmountInProductListing,
                $showInstallmentAmountInCartPage,
                $miniWidgetSelector,
                $widgetStyles,
                $productPriceSelector,
                $defaultProductLocationSelector,
                $cartPriceSelector,
                $cartLocationSelector,
                $widgetOnCartPage,
                $listingPriceSelector,
                $listingLocationSelector,
                $widgetOnListingPage,
                $altProductPriceSelector,
                $altProductPriceTriggerSelector,
                isset($labels['message']) ? [$storeConfig->getLocale() => $labels['message']] : [],
                isset($labels['messageBelowLimit']) ? [$storeConfig->getLocale() => $labels['messageBelowLimit']] : [],
                $customLocations
            )
        );

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
