<?php

namespace Sequra\Core\Block\Adminhtml\Form\Element;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\PaymentMethodsService;

class PaymentMethods extends Template
{

    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
    /**
     * PaymentMethods constructor.
     *
     * @param Context $context
     * @param mixed[] $data
     */
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }
    // phpcs:enable

    /**
     *
     * @var string
     */
    protected $_template = 'Sequra_Core::form/element/methods.phtml';

    /**
     * Retrieves all payment methods configured for all connected stores.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getPaymentMethods(): array
    {
        return $this->getPaymentMethodsService()->getPaymentMethods();
    }

    /**
     * Get the payment method service.
     *
     * @return PaymentMethodsService
     */
    private function getPaymentMethodsService(): PaymentMethodsService
    {
        return ServiceRegister::getService(PaymentMethodsService::class);
    }
}
