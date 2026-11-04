<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Services\ExpressCheckoutService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings as Settings;

class TermsAndConditionsUrlReader
{
    /**
     * Reads the express checkout terms URL of a store.
     *
     * @param string $storeId
     *
     * @return string|null
     */
    public function read(string $storeId): ?string
    {
        try {
            $settings = StoreContext::doWithStore($storeId, static function () {
                /** @var ExpressCheckoutService $service */
                $service = ServiceRegister::getService(ExpressCheckoutService::class);

                return $service->getExpressCheckoutSettings();
            });
        } catch (Exception $exception) {
            Logger::logWarning(
                'Express Checkout terms and conditions URL could not be read: ' . $exception->getMessage()
            );

            return null;
        }

        $url = $settings instanceof Settings ? $settings->toArray()[Settings::TERMS_AND_CONDITIONS_URL] : null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
