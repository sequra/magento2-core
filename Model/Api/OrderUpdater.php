<?php

namespace Sequra\Core\Model\Api;

use RuntimeException;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\OrderManagementController;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Responses\OrderUpdateResponse;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;

/**
 * Sends order updates to seQura through the integration-core order management Admin API.
 */
class OrderUpdater
{
    /**
     * Submits the order update for the store and returns the order as seQura knows it afterwards.
     *
     * The Admin API answers a failure with an unsuccessful response instead of throwing, so it is turned back
     * into an exception here for the callers that block or log on a failed update.
     *
     * @param string $storeId
     * @param OrderUpdateRequest $request
     *
     * @return SeQuraOrder
     *
     * @throws RuntimeException When seQura did not accept the update.
     */
    public function update(string $storeId, OrderUpdateRequest $request): SeQuraOrder
    {
        /** @var OrderManagementController $orderManagementController */
        $orderManagementController = AdminAPI::get()->orderManagement($storeId);
        /** @var OrderUpdateResponse $response */
        $response = $orderManagementController->updateOrder($request);

        if (!$response->isSuccessful()) {
            $error = $response->toArray();
            $message = isset($error['errorMessage']) && is_string($error['errorMessage'])
                ? $error['errorMessage']
                : 'The order update was not accepted.';

            throw new RuntimeException($message);
        }

        return $response->getOrder();
    }
}
