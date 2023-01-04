<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class CurrencyValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    /**
     * @var \Sequra\Core\Model\Config
     */
    private $config;

    /**
     * @var IpAddressValidator
     */
    private $ipAddressValidator;

    /**
     * @param ResultInterfaceFactory             $resultFactory
     * @param \Sequra\Core\Model\Config $config
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        \Sequra\Core\Model\Config $config,
        IpAddressValidator $ipAddressValidator
    ) {
        $this->config = $config;
        parent::__construct($resultFactory);
        $this->ipAddressValidator = $ipAddressValidator;
    }

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $storeId = $validationSubject['storeId'];
        if (!!$this->config->getCoreValue('currency', $storeId)) {
            $availableCurrencies = explode(
                ',',
                $this->config->getCoreValue('currency', $storeId)?:""
            );

            if (!in_array($validationSubject['currency'], $availableCurrencies)) {
                $isValid =  false;
            }
        }
        $result = $this->ipAddressValidator->validate($validationSubject);
        $isValid = $isValid && $result->isValid();
        return $this->createResult($isValid);
    }
}
