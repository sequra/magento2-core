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
use SeQura\Core\BusinessLogic\AdminAPI\PromotionalWidgets\Responses\WidgetSettingsResponse;

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
        /** @var WidgetSettingsResponse $data */
        $data = AdminAPI::get()->widgetConfiguration($this->storeId)->getWidgetSettings();

        $result = $data->toArray();

        if (empty($result)) {
            $result['productPriceSelector'] = '.price-container .price';
            $result['defaultProductLocationSelector'] = '.actions .action.primary.tocart';
            $result['altProductPriceSelector'] = '[data-price-type="finalPrice"] .price';
            $result['altProductPriceTriggerSelector'] = '.bundle-actions';
            $result['cartPriceSelector'] = '.grand.totals .price';
            $result['cartLocationSelector'] = '.cart-summary';

            return $this->result->setData($result);
        }

        $result['widgetStyles'] = $result['widgetConfiguration'];
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
         * @var string $widgetOnListingPage
         */
        $widgetOnListingPage = $data['widgetOnListingPage'] ?? '';
        /**
         * @var array<int, array<string, bool|string>> $customLocations
         */
        $customLocations = $data['customLocations'] ?? [];

        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->widgetConfiguration($this->storeId)->setWidgetSettings(
            new WidgetSettingsRequest(
                $displayWidgetOnProductPage,
                $showInstallmentAmountInProductListing,
                $showInstallmentAmountInCartPage,
                $widgetStyles,
                $productPriceSelector,
                $defaultProductLocationSelector,
                $cartPriceSelector,
                $cartLocationSelector,
                $widgetOnCartPage,
                $widgetOnListingPage,
                '',
                '',
                $altProductPriceSelector,
                $altProductPriceTriggerSelector,
                $customLocations
            )
        );

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
