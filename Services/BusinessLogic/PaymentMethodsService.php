<?php

namespace Sequra\Core\Services\BusinessLogic;

use Exception;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
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

        foreach ($stores as $store) {
            $countryConfigurations = AdminAPI::get()->countryConfiguration($store)
                ->getCountryConfigurations()->toArray();
            $firstConfig = array_shift($countryConfigurations);

            if (!$firstConfig || isset($firstConfig['errorCode'])) {
                continue;
            }

            $widgetsConfig = AdminAPI::get()->widgetConfiguration($store)->getWidgetSettings()->toArray();

            if (isset($widgetsConfig['errorCode']) || !$widgetsConfig['useWidgets']) {
                continue;
            }

            $paymentProducts = AdminAPI::get()->paymentMethods($store)
                ->getPaymentMethods($firstConfig['merchantId'])->toArray();

            if (!$paymentProducts || isset($paymentProducts['errorCode'])) {
                continue;
            }

            foreach ($paymentProducts as $product) {
                $result[$store][] = [
                    'product' => $product['product'],
                    'title' => $product['title'],
                    'campaign' => $product['campaign']
                ];
            }
        }

        return $result;
    }

    /**
     * @return StoreServiceInterface
     */
    private function getStoreService(): StoreServiceInterface
    {
        return ServiceRegister::getService(StoreServiceInterface::class);
    }
}