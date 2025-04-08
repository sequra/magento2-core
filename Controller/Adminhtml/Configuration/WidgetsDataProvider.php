<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Sequra\Core\Services\BusinessLogic\PaymentMethodsService;
use Sequra\Core\Services\BusinessLogic\WidgetConfigService;

class WidgetsDataProvider extends BaseConfigurationController
{
    /**
     * @var WidgetConfigService
     */
    private $widgetConfigService;
    /**
     * @var PaymentMethodsService
     */
    private $paymentMethodsService;

    /**
     * @param WidgetConfigService $widgetConfigService
     * @param PaymentMethodsService $paymentMethodsService
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        WidgetConfigService $widgetConfigService,
        PaymentMethodsService $paymentMethodsService,
        Context $context,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $jsonFactory);

        $this->allowedActions = ['getData'];
        $this->widgetConfigService = $widgetConfigService;
        $this->paymentMethodsService = $paymentMethodsService;
    }

    /**
     * Get the data for the widget configuration.
     *
     * @return Json
     *
     * @throws HttpRequestException
     */
    protected function getData(): Json
    {
        $config = $this->widgetConfigService->getData();
        $config['methodsPerStore'] = $this->paymentMethodsService->getPaymentMethods();

        return $this->result->setData(['widgetConfig' => $config]);
    }
}
