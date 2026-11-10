<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Exceptions;

use SeQura\Core\BusinessLogic\Domain\Translations\Model\BaseTranslatableException;
use SeQura\Core\BusinessLogic\Domain\Translations\Model\TranslatableLabel;
use Throwable;

/**
 * Thrown when the merchant terms URL is not an absolute http or https URL.
 */
class InvalidTermsAndConditionsUrlException extends BaseTranslatableException
{
    /**
     * @var int
     */
    protected $code = 400;

    /**
     * @param ?Throwable $previous
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(new TranslatableLabel(
            'Invalid terms and conditions URL.',
            'general.errors.expressCheckout.invalidTermsAndConditionsUrl'
        ), $previous);
    }
}
