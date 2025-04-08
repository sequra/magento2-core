<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\PaymentMethods\Responses\PaymentMethodsResponse;
use Sequra\Core\DataAccess\Entities\PaymentMethod;
use Sequra\Core\DataAccess\Entities\PaymentMethods as PaymentMethodsEntity;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;

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
        $data = AdminAPI::get()->paymentMethods($this->storeId)->getPaymentMethods($this->identifier);
        $this->savePaymentMethods($data);

        $this->addResponseCode($data);

        return $this->result->setData($data->toArray());
    }

    /**
     * Saves payment methods to the integration database.
     *
     * @param PaymentMethodsResponse $data
     *
     * @return void
     *
     * @throws RepositoryNotRegisteredException
     */
    private function savePaymentMethods(PaymentMethodsResponse $data): void
    {
        $apiPaymentMethods = PaymentMethod::fromBatch($data->toArray());

        $paymentMethodsRepository = RepositoryRegistry::getRepository(PaymentMethodsEntity::CLASS_NAME);

        $filter = new QueryFilter();
        $filter->where('storeId', Operators::EQUALS, $this->storeId)
            ->where('merchantId', Operators::EQUALS, $this->identifier);
        $paymentMethods = $paymentMethodsRepository->selectOne($filter);

        if ($paymentMethods === null) {
            $paymentMethods = new PaymentMethodsEntity();

            $paymentMethods->setStoreId($this->storeId);
            $paymentMethods->setMerchantId($this->identifier);
            $paymentMethods->setPaymentMethods($apiPaymentMethods);

            $paymentMethodsRepository->save($paymentMethods);
        } else {
            $paymentMethods->setPaymentMethods($apiPaymentMethods);
            $paymentMethodsRepository->update($paymentMethods);
        }
    }
}
