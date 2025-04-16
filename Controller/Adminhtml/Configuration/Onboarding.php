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
        // @phpstan-ignore-next-line
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
        /**
         * @var array<string, string|bool>
         */
        $data = $this->getSequraPostData();

        /**
         * @var string $environment
         */
        $environment = $data['environment'] ?? '';
        /**
         * @var string $username
         */
        $username = $data['username'] ?? '';
        /**
         * @var string $password
         */
        $password = $data['password'] ?? '';
        /**
         * @var bool $sendStatisticalData
         */
        $sendStatisticalData = $data['sendStatisticalData'] ?? true;

        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->connection($this->storeId)->saveOnboardingData(new OnboardingRequest(
            $environment,
            $username,
            $password,
            $sendStatisticalData
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
        /**
         * @var array<string, string>
         */
        $data = $this->getSequraPostData();
        /**
         * @var string $environment
         */
        $environment = $data['environment'] ?? '';
        /**
         * @var string $merchantId
         */
        $merchantId = $data['merchantId'] ?? '';
        /**
         * @var string $username
         */
        $username = $data['username'] ?? '';
        /**
         * @var string $password
         */
        $password = $data['password'] ?? '';

        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->connection($this->storeId)->isConnectionDataValid(new ConnectionRequest(
            $environment,
            $merchantId,
            $username,
            $password
        ));

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
