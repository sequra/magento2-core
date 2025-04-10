<?php

namespace Sequra\Core\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Sales\Model\Order;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Sequra\Core\Services\BusinessLogic\Utility\TransformEntityService;

/**
 * Class TransactionSale
 */
class OrderUpdateTransaction implements ClientInterface
{
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * @var TransformEntityService
     */
    private $transformService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * Constructor
     *
     * @param SeQuraTranslationProvider $translationProvider
     * @param TransformEntityService $transformService
     */
    public function __construct(
        SeQuraTranslationProvider $translationProvider,
        TransformEntityService $transformService
    ) {
        $this->translationProvider = $translationProvider;
        $this->transformService = $transformService;
    }

    /**
     * Place request
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        /** @var Order $order */
        $order = $transferObject->getBody()['order'];
        if (!$order) {
            Logger::logError('Order synchronization for refund failed. Missing refund request data.', 'Integration');

            return [
                'success' => false,
                'errorMessage' => $this->translationProvider->translate('sequra.error.cannotRefund')
            ];
        }

        if ($order->getStatus() === Order::STATE_PAYMENT_REVIEW) {
            Logger::logError(
                'Order synchronization for refund failed. Not possible to refund order in payment review status.',
                'Integration'
            );

            return [
                'success' => false,
                'errorMessage' => $this->translationProvider->translate('sequra.error.cannotRefund')
            ];
        }

        $shippedCart = $this->transformService->transformOrderCartToSeQuraCart($order, true);
        $unshippedCart = $this->transformService->transformOrderCartToSeQuraCart($order, false);

        try {
            $sequraOrder = StoreContext::doWithStore($order->getStoreId(), [$this->getOrderService(), 'updateOrder'], [
                new OrderUpdateData(
                    $order->getIncrementId(),
                    $shippedCart,
                    $unshippedCart,
                    null,
                    null
                )
            ]);
        } catch (\Exception $exception) {
            Logger::logError(
                'Order synchronization for refund failed. Order update request failed.',
                'Integration',
                [
                    'ExceptionMessage' => $exception->getMessage(),
                    'ExceptionTrace' => $exception->getTraceAsString(),
                ]
            );

            return [
                'success' => false,
                'errorMessage' => $this->translationProvider->translate('sequra.error.cannotRefund')
            ];
        }

        return [
            "success" => true,
            "data" => $sequraOrder
        ];
    }

    /**
     * Returns an instance of Order service.
     *
     * @return OrderService
     */
    private function getOrderService(): OrderService
    {
        if (!isset($this->orderService)) {
            $this->orderService = ServiceRegister::getService(OrderService::class);
        }

        return $this->orderService;
    }
}
