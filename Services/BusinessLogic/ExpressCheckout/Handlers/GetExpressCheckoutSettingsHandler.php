<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Handlers;

use SeQura\Core\BusinessLogic\AdminAPI\Response\Response;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Handlers\ExpressCheckout as CoreHandlers;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Responses\GetExpressCheckoutSettingsResponse;

/**
 * Returns the express checkout settings to the portal, merchant terms URL included.
 */
class GetExpressCheckoutSettingsHandler extends CoreHandlers\GetExpressCheckoutSettingsHandler
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
