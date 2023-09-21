<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\CountryConfiguration\Requests\CountryConfigurationRequest;

/**
 * Class CountrySettings
 *
 * @package Sequra\Core\Controller\Adminhtml\Configuration
 */
class CountrySettings extends BaseConfigurationController
{
    /**
     * CountrySettings constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getSellingCountries', 'getCountrySettings', 'setCountrySettings'];
    }

    /**
     * Returns all available selling countries.
     *
     * @return Json
     */
    protected function getSellingCountries(): Json
    {
        $data = AdminAPI::get()->countryConfiguration($this->storeId)->getSellingCountries();

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns existing country configuration.
     *
     * @return Json
     */
    protected function getCountrySettings(): Json
    {
        $data = AdminAPI::get()->countryConfiguration($this->storeId)->getCountryConfigurations();

        return $this->result->setData($data->toArray());
    }

    /**
     * Sets new country configuration.
     *
     * @return Json
     */
    protected function setCountrySettings(): Json
    {
        $response = AdminAPI::get()->countryConfiguration($this->storeId)->saveCountryConfigurations(
            new CountryConfigurationRequest($this->getSequraPostData())
        );

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
