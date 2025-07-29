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

        $this->allowedActions = ['getConnectionData', 'connect', 'validateConnectionData'];
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
    protected function connect(): Json
    {
        /**
         * @var array{
         *     environment?: string,
         *     sendStatisticalData?: bool,
         *     connectionData?: array<array{
         *         merchantId?: string,
         *         username?: string,
         *         password?: string,
         *         deployment?: string
         *     }>
         * } $data
         */
        $data = $this->getSequraPostData();

        /**
         * @var bool $sendStatisticalData
         */
        $sendStatisticalData = $data['sendStatisticalData'] ?? true;

        /**
         * @var array<array{merchantId?: string,
         *     username?: string,
         *     password?: string,
         *     deployment?: string}> $connectionDataArray
         */
        $connectionDataArray = $data['connectionData'] ?? [];

        /**
         * @var string $environment
         */
        $environment = $data['environment'] ?? '';

        $connectionRequests = [];
        foreach ($connectionDataArray as $connData) {
            $connectionRequests[] = new ConnectionRequest(
                $environment,
                $connData['merchantId'] ?? '',
                $connData['username'] ?? '',
                $connData['password'] ?? '',
                $connData['deployment'] ?? ''
            );
        }

        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->connection($this->storeId)->connect(new OnboardingRequest(
            $connectionRequests,
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

        /**
         * @var string $deploymentId
         */
        $deploymentId = $data['deploymentId'] ?? '';

        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->connection($this->storeId)->isConnectionDataValid(new ConnectionRequest(
            $environment,
            $merchantId,
            $username,
            $password,
            $deploymentId
        ));

        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
