<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models;

use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Exceptions\DuplicatedExpressCheckoutPageException;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Exceptions\InvalidExpressCheckoutButtonStyleException;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Exceptions\InvalidExpressCheckoutPageConfigException;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPageConfig;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutSettings as CoreExpressCheckoutSettings;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Exceptions\InvalidTermsAndConditionsUrlException;

class ExpressCheckoutSettings extends CoreExpressCheckoutSettings
{
    public const TERMS_AND_CONDITIONS_URL = 'termsAndConditionsUrl';

    /**
     * @var string|null
     */
    protected $termsAndConditionsUrl;

    /**
     * @param ExpressCheckoutPageConfig[] $expressCheckoutConfigs
     * @param string|null $buttonStyle
     * @param mixed $termsAndConditionsUrl
     *
     * @throws InvalidExpressCheckoutPageConfigException
     * @throws DuplicatedExpressCheckoutPageException
     * @throws InvalidExpressCheckoutButtonStyleException
     * @throws InvalidTermsAndConditionsUrlException
     */
    public function __construct(
        array $expressCheckoutConfigs = [],
        ?string $buttonStyle = null,
        $termsAndConditionsUrl = null
    ) {
        parent::__construct($expressCheckoutConfigs, $buttonStyle);

        if ($termsAndConditionsUrl === null) {
            return;
        }

        if (!is_string($termsAndConditionsUrl)) {
            throw new InvalidTermsAndConditionsUrlException();
        }

        $url = trim($termsAndConditionsUrl);
        if ($url === '') {
            return;
        }

        if (preg_match('/[\s\x00-\x1F\x7F]/u', $url) !== 0
            || preg_match('#^https?://(?:[^/?\#@]*@)?[^/?\#@:]+(?::\d*)?(?:[/?\#]|$)#iu', $url) !== 1
        ) {
            throw new InvalidTermsAndConditionsUrlException();
        }

        $this->termsAndConditionsUrl = $url;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data[self::TERMS_AND_CONDITIONS_URL] = $this->termsAndConditionsUrl;

        return $data;
    }
}
