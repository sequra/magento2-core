<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class GeneralResponseValidator extends AbstractValidator
{
    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(ResultInterfaceFactory $resultFactory)
    {
        parent::__construct($resultFactory);
    }
    // phpcs:enable

    /**
     * @inheritdoc
     *
     * @param array $validationSubject
     * @phpstan-param array<string, array<string, string>> $validationSubject
     */
    public function validate(array $validationSubject)
    {
        $response = $validationSubject['response'];

        $isValid = true;
        $errorMessages = [];
        $errorCodes = [];
        if (!empty($response['errorMessage'])) {
            $isValid = false;
            $errorMessages[] = $response['errorMessage'];
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
