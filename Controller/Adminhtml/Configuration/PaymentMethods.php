<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\PaymentMethods\Requests\GetPaymentMethodsRequest;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;

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
     *
     * @throws HttpRequestException
     * @throws RepositoryNotRegisteredException
     * @throws \Exception
     */
    protected function getPaymentMethods(): Json
    {
        $data = AdminAPI::get()->paymentMethods($this->storeId)->getPaymentMethods(
            new GetPaymentMethodsRequest((string) $this->identifier, true)
        );
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }
}
