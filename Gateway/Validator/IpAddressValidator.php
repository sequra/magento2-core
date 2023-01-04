<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class IpAddressValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{

    /**
     * @var \Sequra\Core\Model\Config
     */
    private $config;

    /**
     * @param ResultInterfaceFactory             $resultFactory
     * @param \Sequra\Core\Model\Config $config
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        \Sequra\Core\Model\Config $config
    ) {
        $this->config = $config;
        parent::__construct($resultFactory);
    }

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $storeId = $validationSubject['storeId'];
        if (!!$this->config->getCoreValue('test_ip', $storeId)) {
            $availableIps = explode(
                ',',
                $this->config->getCoreValue('test_ip', $storeId)?:""
            );

            if (!in_array($_SERVER['REMOTE_ADDR'], $availableIps)) {
                $isValid =  false;
            }
        }
        return $this->createResult($isValid);
    }
}
