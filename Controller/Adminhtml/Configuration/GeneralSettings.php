<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\GeneralSettings\Requests\GeneralSettingsRequest;

/**
 * Class GeneralSettings
 *
 * @package Sequra\Core\Controller\Adminhtml\Configuration
 */
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
        $data = AdminAPI::get()->generalSettings($this->storeId)->getShopCategories();

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns existing general settings.
     *
     * @return Json
     */
    protected function getGeneralSettings(): Json
    {
        $data = AdminAPI::get()->generalSettings($this->storeId)->getGeneralSettings();

        return $this->result->setData($data->toArray());
    }

    /**
     * Sets new general settings.
     *
     * @return Json
     */
    protected function setGeneralSettings(): Json
    {
        $data = $this->getSequraPostData();
        $response = AdminAPI::get()->generalSettings($this->storeId)->saveGeneralSettings(new GeneralSettingsRequest(
            $data['showSeQuraCheckoutAsHostedPage'],
            $data['sendOrderReportsPeriodicallyToSeQura'],
            $data['allowedIPAddresses'],
            $data['excludedProducts'],
            $data['excludedCategories']
        ));

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
