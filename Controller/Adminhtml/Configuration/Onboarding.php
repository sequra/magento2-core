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

        $this->allowedActions = ['getConnectionData', 'connect'];
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
        $response = AdminAPI::get()->connection($this->storeId)->connect(new OnboardingRequest($connectionRequests));
        $this->addResponseCode($response);

        return $this->result->setData($response->toArray());
    }
}
