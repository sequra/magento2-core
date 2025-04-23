<?php

namespace Sequra\Core\Services\BusinessLogic;

use Exception;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreServiceInterface;
use Sequra\Core\DataAccess\Entities\PaymentMethod;
use Sequra\Core\DataAccess\Entities\PaymentMethods as PaymentMethodsEntity;
use SeQura\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\ServiceRegister;

class PaymentMethodsService
{
    /**
     * Retrieves payment methods per store.
     *
     * @return array
     * @phpstan-return array<array<int, array<string, string|null>>>
     *
     * @throws HttpRequestException
     * @throws Exception
     */
    public function getPaymentMethods(): array
    {
        $result = [];
        /**
         * TODO: getConnectedStores() should be moved to StoreServiceInterface
         * @var \Sequra\Core\Services\BusinessLogic\StoreService $storeService
         */
        $storeService = $this->getStoreService();
        $stores = $storeService->getConnectedStores();

        foreach ($stores as $storeId) {
            // @phpstan-ignore-next-line
            $countryConfigurations = AdminAPI::get()->countryConfiguration($storeId)
                ->getCountryConfigurations()->toArray();
            $firstConfig = array_shift($countryConfigurations);

            if (!$firstConfig || isset($firstConfig['errorCode'])) {
                continue;
            }

            // @phpstan-ignore-next-line
            $widgetsConfig = AdminAPI::get()->widgetConfiguration($storeId)->getWidgetSettings()->toArray();

            if (isset($widgetsConfig['errorCode']) || !$widgetsConfig['useWidgets']) {
                continue;
            }

            $paymentProducts = $this->getPaymentProducts($storeId, $firstConfig['merchantId']);

            if (!$paymentProducts || isset($paymentProducts['errorCode'])) {
                continue;
            }

            foreach ($paymentProducts as $product) {
                $result[$storeId][] = [
                    'product' => $product->getProduct(),
                    'title' => $product->getTitle(),
                    'campaign' => $product->getCampaign(),
                ];
            }
        }

        return $result;
    }

    /**
     * Retrieves payment products for a specific store.
     *
     * @param string $storeId
     * @param string $merchantId
     *
     * @return PaymentMethod[]
     *
     * @throws \SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getPaymentProducts(string $storeId, string $merchantId): array
    {
        $paymentMethodsRepository = RepositoryRegistry::getRepository(PaymentMethodsEntity::CLASS_NAME);

        $filter = new QueryFilter();
        $filter->where('storeId', Operators::EQUALS, $storeId)
            ->where('merchantId', Operators::EQUALS, $merchantId);

        /** @var PaymentMethodsEntity $paymentMethods */
        $paymentMethods = $paymentMethodsRepository->selectOne($filter);

        if ($paymentMethods === null) {
            return [];
        }

        return $paymentMethods->getPaymentMethods();
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
