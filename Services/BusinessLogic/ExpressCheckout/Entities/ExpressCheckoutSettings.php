<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities;

use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Entities\ExpressCheckoutSettings as CoreEntity;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Exceptions\InvalidTermsAndConditionsUrlException;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings as Settings;

class ExpressCheckoutSettings extends CoreEntity
{
    public const CLASS_NAME = __CLASS__;

    /**
     * @inheritDoc
     */
    public function inflate(array $data): void
    {
        parent::inflate($data);

        $core = $this->getExpressCheckoutSettings();
        $raw = is_array($data['expressCheckoutSettings'] ?? null) ? $data['expressCheckoutSettings'] : [];

        try {
            $settings = new Settings(
                $core->getExpressCheckoutConfigs(),
                $core->getButtonStyle(),
                static::getDataValue($raw, Settings::TERMS_AND_CONDITIONS_URL, null)
            );
        } catch (InvalidTermsAndConditionsUrlException $exception) {
            $settings = new Settings($core->getExpressCheckoutConfigs(), $core->getButtonStyle());
        }

        $this->setExpressCheckoutSettings($settings);
    }
}
