<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\Connection\Requests\ReRegisterWebhookRequest;

class Connection extends BaseConfigurationController
{
    /**
     * Disconnect constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['reRegisterWebhooks'];
    }

    /**
     * Re-register webhooks on the Merchant Portal.
     *
     * @return Json
     */
    protected function reRegisterWebhooks(): Json
    {
        /**
         * @var array<string> $data
         */
        $data = $this->getSequraPostData();
        $environment = $data['environment'] ?? '';
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $deployment = $data['deployment'] ?? '';

        // @phpstan-ignore-next-line
        $response = AdminAPI::get()->connection($this->storeId)->reRegisterWebhooks(
            new ReRegisterWebhookRequest(
                $environment,
                '',
                $username,
                $password,
                $deployment
            )
        );
        $this->addResponseCode($response);

        return $this->result->setData(['isSuccessful' => $response->isSuccessful()]);
    }
}
