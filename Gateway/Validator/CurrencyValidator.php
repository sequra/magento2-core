<?php
namespace Sequra\Core\Gateway\Validator;

use Sequra\Core\Logger\Logger;

class CurrencyValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        return $this->createResult(in_array($validationSubject['currency'], ['EUR'], true));
    }
}
