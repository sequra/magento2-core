<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Handlers;

use SeQura\Core\BusinessLogic\AdminAPI\Response\Response;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Handlers\ExpressCheckout\SaveExpressCheckoutSettingsHandler as Core;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Requests\ExpressCheckout\SaveExpressCheckoutSettingsRequest;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Responses\ExpressCheckout\SaveExpressCheckoutSettingsResponse;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Exceptions\InvalidTermsAndConditionsUrlException;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings as Settings;

class SaveExpressCheckoutSettingsHandler extends Core
{
    /**
     * @inheritDoc
     *
     * @throws InvalidTermsAndConditionsUrlException
     */
    public function handle(array $payload): Response
    {
        $settings = SaveExpressCheckoutSettingsRequest::fromPayload($payload)->transformToDomainModel();

        if (array_key_exists(Settings::TERMS_AND_CONDITIONS_URL, $payload)) {
            $settings = new Settings(
                $settings->getExpressCheckoutConfigs(),
                $settings->getButtonStyle(),
                $payload[Settings::TERMS_AND_CONDITIONS_URL]
            );
        }

        $this->expressCheckoutService->saveExpressCheckoutSettings($settings);

        return new SaveExpressCheckoutSettingsResponse();
    }
}
