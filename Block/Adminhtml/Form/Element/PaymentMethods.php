<?php

namespace Sequra\Core\Block\Adminhtml\Form\Element;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\PaymentMethodsService;

/**
 * Class PaymentMethods
 *
 * @package Sequra\Core\Block\Adminhtml\Form\Element
 */
class PaymentMethods extends Template
{
    /**
     *
     * @var string
     */
    protected $_template = 'Sequra_Core::form/element/methods.phtml';

    /**
     * PaymentMethods constructor.
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array   $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * Retrieves all payment methods configured for all connected stores.
     *
     * @return array
     *
     * @throws Exception
     */
    public function getPaymentMethods(): array
    {
        return $this->getPaymentMethodsService()->getPaymentMethods();
    }

    /**
     * @return PaymentMethodsService
     */
    private function getPaymentMethodsService(): PaymentMethodsService
    {
        return ServiceRegister::getService(PaymentMethodsService::class);
    }
}
