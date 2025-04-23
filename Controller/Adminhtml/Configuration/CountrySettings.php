<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\CountryConfiguration\Requests\CountryConfigurationRequest;

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
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->countryConfiguration($this->storeId)->getSellingCountries();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns existing country configuration.
     *
     * @return Json
     */
    protected function getCountrySettings(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->countryConfiguration($this->storeId)->getCountryConfigurations();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Sets new country configuration.
     *
     * @return Json
     */
    protected function setCountrySettings(): Json
    {
        /**
         * @var array<int, array<string, string>>
         */
        $data = $this->getSequraPostData();
        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->countryConfiguration($this->storeId)->saveCountryConfigurations(
            new CountryConfigurationRequest($data)
        );

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
