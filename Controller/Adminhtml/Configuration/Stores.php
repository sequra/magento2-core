<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;

class Stores extends BaseConfigurationController
{
    /**
     * Stores constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getStores', 'getCurrentStore'];
    }

    /**
     * Returns all stores.
     *
     * @return Json
     */
    protected function getStores(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->store($this->storeId)->getStores();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns the current active store.
     *
     * @return Json
     */
    protected function getCurrentStore(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->store($this->storeId)->getCurrentStore();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }
}
