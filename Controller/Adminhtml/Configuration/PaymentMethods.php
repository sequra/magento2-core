<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\PaymentMethods\Requests\GetFormattedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\AdminAPI\PaymentMethods\Requests\GetPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Exceptions\PaymentMethodNotFoundException;
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

        $this->allowedActions = ['getPaymentMethods', 'getAllAvailablePaymentMethods'];
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

    /**
     * Returns available payment methods for all merchants, grouped by payment method category.
     *
     * @return Json
     *
     * @throws HttpRequestException
     * @throws PaymentMethodNotFoundException
     */
    protected function getAllAvailablePaymentMethods(): Json
    {
        $data = AdminAPI::get()->paymentMethods($this->storeId)->getAllAvailablePaymentMethods(
            new GetFormattedPaymentMethodsRequest(true)
        );
        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }
}
