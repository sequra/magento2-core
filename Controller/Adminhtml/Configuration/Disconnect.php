<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\Disconnect\Requests\DisconnectRequest;

class Disconnect extends BaseConfigurationController
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

        $this->allowedActions = ['disconnect'];
    }

    /**
     * Disconnects integration from the shop.
     *
     * @return Json
     */
    protected function disconnect(): Json
    {
        /**
         * @var array<string, string|bool>
         */
        $data = $this->getSequraPostData();

        /**
         * @var bool $fullDisconnect
         */
        $fullDisconnect = $data['isFullDisconnect'] ?? '';

        /**
         * @var string $deploymentId
         */
        $deploymentId = $data['deploymentId'] ?? '';

        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->disconnect($this->storeId)->disconnect(
            new DisconnectRequest($deploymentId, $fullDisconnect));

        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }
}
