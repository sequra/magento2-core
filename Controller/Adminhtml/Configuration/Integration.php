<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;

class Integration extends BaseConfigurationController
{
    /**
     * Integration constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getVersion', 'getState', 'getShopName'];
    }

    /**
     * Returns integration version information.
     *
     * @return Json
     */
    protected function getVersion(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->integration($this->storeId)->getVersion();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns the integration UI state.
     *
     * @return Json
     */
    protected function getState(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->integration($this->storeId)->getUIState();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Returns the integration shop name.
     *
     * @return Json
     */
    protected function getShopName(): Json
    {
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->integration($this->storeId)->getShopName();
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }
}
