<?php

namespace Sequra\Core\Model\Api;

use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use Sequra\Core\Model\Api\Exception\OrderUpdateFailedException;

/**
 * Sends order updates to seQura through the integration-core order management Admin API.
 */
class OrderUpdater
{
    /**
     * Submits the order update for the store and returns the order as seQura knows it afterwards.
     *
     * The Admin API answers a failure with an unsuccessful response instead of throwing, so it is turned back
     * into an exception here, carrying the error message and code of the response.
     *
     * @param string $storeId
     * @param OrderUpdateRequest $request
     *
     * @return SeQuraOrder
     *
     * @throws OrderUpdateFailedException When seQura or integration-core did not accept the update.
     */
    public function update(string $storeId, OrderUpdateRequest $request): SeQuraOrder
    {
        $response = AdminAPI::get()->orderManagement($storeId)->updateOrder($request);

        if (!$response->isSuccessful()) {
            $error = $response->toArray();
            $message = $error['errorMessage'] ?? '';
            $code = $error['errorCode'] ?? '';

            throw new OrderUpdateFailedException(
                is_string($message) && $message !== '' ? $message : 'The order update was not accepted.',
                (is_string($code) || is_int($code)) && $code !== 0 ? (string)$code : ''
            );
        }

        return $response->getOrder();
    }
}
