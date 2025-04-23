<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\GeneralSettings\Requests\GeneralSettingsRequest;

class GeneralSettings extends BaseConfigurationController
{
    /**
     * GeneralSettings constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getShopCategories', 'getGeneralSettings', 'setGeneralSettings'];
    }

    /**
     * Returns all shop categories.
     *
     * @return Json
     */
    protected function getShopCategories(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->generalSettings($this->storeId)->getShopCategories();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns existing general settings.
     *
     * @return Json
     */
    protected function getGeneralSettings(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->generalSettings($this->storeId)->getGeneralSettings();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Sets new general settings.
     *
     * @return Json
     */
    protected function setGeneralSettings(): Json
    {
        /**
         * @var array<string, null|bool|array<string>> $data
         */
        $data = $this->getSequraPostData();
        /**
         * @var bool $sendOrderReportsPeriodicallyToSeQura
         */
        $sendOrderReportsPeriodicallyToSeQura = $data['sendOrderReportsPeriodicallyToSeQura'] ?? true;
        /**
         * @var bool $showSeQuraCheckoutAsHostedPage
         */
        $showSeQuraCheckoutAsHostedPage = $data['showSeQuraCheckoutAsHostedPage'] ?? false;
        /**
         * @var array<string> $allowedIPAddresses
         */
        $allowedIPAddresses = $data['allowedIPAddresses'] ?? [];
        /**
         * @var array<string> $excludedProducts
         */
        $excludedProducts = $data['excludedProducts'] ?? [];
        /**
         * @var array<string> $excludedCategories
         */
        $excludedCategories = $data['excludedCategories'] ?? [];
        
        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->generalSettings($this->storeId)->saveGeneralSettings(new GeneralSettingsRequest(
            $sendOrderReportsPeriodicallyToSeQura,
            $showSeQuraCheckoutAsHostedPage,
            $allowedIPAddresses,
            $excludedProducts,
            $excludedCategories
        ));

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
