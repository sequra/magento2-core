<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Handlers;

use SeQura\Core\BusinessLogic\AdminAPI\Response\Response;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Handlers\ExpressCheckout\GetExpressCheckoutSettingsHandler as Core;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Responses\GetExpressCheckoutSettingsResponse;

class GetExpressCheckoutSettingsHandler extends Core
{
    /**
     * @inheritDoc
     */
    public function handle(array $payload): Response
    {
        return new GetExpressCheckoutSettingsResponse(
            $this->expressCheckoutIntegration->getAvailablePages(),
            $this->expressCheckoutService->getExpressCheckoutSettings()
        );
    }
}
