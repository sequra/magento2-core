<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Responses;

use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Responses\ExpressCheckout as CoreResponses;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings as Settings;

class GetExpressCheckoutSettingsResponse extends CoreResponses\GetExpressCheckoutSettingsResponse
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $settings = $this->expressCheckoutSettings ? $this->expressCheckoutSettings->toArray() : [];
        $data = parent::toArray();
        $data[Settings::TERMS_AND_CONDITIONS_URL] = $settings[Settings::TERMS_AND_CONDITIONS_URL] ?? null;

        return $data;
    }
}
