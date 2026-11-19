<?php

namespace Sequra\Core\Services\BusinessLogic;

use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;
use SeQura\Core\BusinessLogic\Domain\Integration\ExpressCheckout\ExpressCheckoutIntegrationInterface;

/**
 * Class ExpressCheckoutIntegrationService
 *
 * Declares the storefront pages on which this integration is capable of hosting
 * Express Checkout buttons.
 */
class ExpressCheckoutIntegrationService implements ExpressCheckoutIntegrationInterface
{
    /**
     * Returns the pages supported by the platform integration.
     *
     * @return ExpressCheckoutPage[]
     */
    public function getAvailablePages(): array
    {
        return [
            ExpressCheckoutPage::product(),
            ExpressCheckoutPage::cart(),
            ExpressCheckoutPage::miniCart(),
        ];
    }
}
