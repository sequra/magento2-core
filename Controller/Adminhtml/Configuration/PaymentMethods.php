<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;

/**
 * Class PaymentMethods
 *
 * @package Sequra\Core\Controller\Adminhtml\Configuration
 */
class PaymentMethods extends BaseConfigurationController
{
    /**
     * PaymentMethods constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getPaymentMethods'];
    }

    /**
     * Returns active connection data.
     *
     * @return Json
     */
    protected function getPaymentMethods(): Json
    {
        $data = AdminAPI::get()->paymentMethods($this->storeId)->getPaymentMethods($this->identifier);
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }
}
