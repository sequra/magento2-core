<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;

class DeploymentsSettings extends BaseConfigurationController
{
    /**
     * DeploymentsSettings constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getDeployments', 'getNotConnectedDeployments'];
    }

    /**
     * Returns all deployments options.
     *
     * @return Json
     */
    protected function getDeployments(): Json
    {
        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->deployments($this->storeId)->getAllDeployments();

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }

    /**
     * Returns all deployments options.
     *
     * @return Json
     */
    protected function getNotConnectedDeployments(): Json
    {
        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->deployments($this->storeId)->getNotConnectedDeployments();

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
