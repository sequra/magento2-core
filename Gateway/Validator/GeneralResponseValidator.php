<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class GeneralResponseValidator extends AbstractValidator
{

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $response = $validationSubject['response'];

        $isValid = true;
        $errorMessages = [];
        $errorCodes = [];
        if (!empty($response['errorMessage'])) {
            $isValid = false;
            $errorMessages[] = $validationSubject['response']['errorMessage'];
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
