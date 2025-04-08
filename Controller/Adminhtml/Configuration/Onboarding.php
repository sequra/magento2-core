<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\Connection\Requests\ConnectionRequest;
use SeQura\Core\BusinessLogic\AdminAPI\Connection\Requests\OnboardingRequest;

class Onboarding extends BaseConfigurationController
{
    /**
     * Onboarding constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getConnectionData', 'setConnectionData', 'validateConnectionData'];
    }

    /**
     * Returns active connection data.
     *
     * @return Json
     */
    protected function getConnectionData(): Json
    {
        $data = AdminAPI::get()->connection($this->storeId)->getOnboardingData();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Sets new connection data.
     *
     * @return Json
     */
    protected function setConnectionData(): Json
    {
        $data = $this->getSequraPostData();
        $response = AdminAPI::get()->connection($this->storeId)->saveOnboardingData(new OnboardingRequest(
            $data['environment'],
            $data['username'],
            $data['password'],
            $data['sendStatisticalData']
        ));

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }

    /**
     * Validates connection data.
     *
     * @return Json
     */
    protected function validateConnectionData(): Json
    {
        $data = $this->getSequraPostData();
        $response = AdminAPI::get()->connection($this->storeId)->isConnectionDataValid(new ConnectionRequest(
            $data['environment'],
            $data['merchantId'],
            $data['username'],
            $data['password']
        ));

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
