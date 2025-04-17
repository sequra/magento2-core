<?php
namespace Sequra\Core\Gateway\Validator;

use Sequra\Core\Logger\Logger;

class CurrencyValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{

    /**
     * @inheritdoc
     *
     * @param array $validationSubject
     * @phpstan-param array<string, mixed> $validationSubject
     */
    public function validate(array $validationSubject)
    {
        $currency = isset($validationSubject['currency'])
        && is_string($validationSubject['currency']) ? $validationSubject['currency'] : '';
        return $this->createResult(in_array($currency, ['EUR'], true));
    }
}
