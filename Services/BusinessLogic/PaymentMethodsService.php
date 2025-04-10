<?php

namespace Sequra\Core\Services\BusinessLogic;

use Exception;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreServiceInterface;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ServiceRegister;

/**
 * Class PaymentMethodsService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class PaymentMethodsService
{
    /**
     * Retrieves payment methods per store.
     *
     * @return array
     *
     * @throws HttpRequestException
     * @throws Exception
     */
    public function getPaymentMethods(): array
    {
        $stores = $this->getStoreService()->getConnectedStores();
        $result = [];

        foreach ($stores as $storeId) {
            $countryConfigurations = AdminAPI::get()->countryConfiguration($storeId)
                ->getCountryConfigurations()->toArray();
            $firstConfig = array_shift($countryConfigurations);

            if (!$firstConfig || isset($firstConfig['errorCode'])) {
                continue;
            }

            $widgetsConfig = AdminAPI::get()->widgetConfiguration($storeId)->getWidgetSettings()->toArray();

            if (isset($widgetsConfig['errorCode']) || !$widgetsConfig['useWidgets']) {
                continue;
            }

            /** @var CachedPaymentMethodsResponse $paymentMethods */
            $paymentMethods = CheckoutAPI::get()->cachedPaymentMethods($storeId)
                ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($firstConfig['merchantId']));

            if (!$paymentMethods->isSuccessful()) {
                continue;
            }

            foreach ($paymentMethods->toArray() as $product) {
                $result[$storeId][] = [
                    'product' => $product['product'],
                    'title' => $product['title'],
                    'campaign' => $product['campaign'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get store service instance.
     *
     * @return StoreServiceInterface
     */
    private function getStoreService(): StoreServiceInterface
    {
        return ServiceRegister::getService(StoreServiceInterface::class);
    }
}
