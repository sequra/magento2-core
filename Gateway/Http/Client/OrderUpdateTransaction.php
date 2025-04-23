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
use SeQura\Core\Infrastructure\Logger\LogContextData;

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
     * @var OrderService|null
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
     * @return array<string, \Magento\Framework\Phrase|bool|\SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder>
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $body = $transferObject->getBody();
        if (!is_array($body) || !isset($body['order']) || !$body['order'] instanceof Order) {
            Logger::logError('Order synchronization for refund failed. Missing refund request data.', 'Integration');
            
            return [
                'success' => false,
                'errorMessage' => $this->translationProvider->translate('sequra.error.cannotRefund')
            ];
        }
        $order = $body['order'];

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
        $sequraOrder = null;
        try {
            /**
            * @var \SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder $sequraOrder
            */
            $sequraOrder = StoreContext::doWithStore(
                (string) $order->getStoreId(),
                [$this->getOrderService(), 'updateOrder'],
                [
                new OrderUpdateData(
                    $order->getIncrementId(),
                    $shippedCart,
                    $unshippedCart,
                    null,
                    null
                )
                ]
            );
        } catch (\Exception $exception) {
            Logger::logError(
                'Order synchronization for refund failed. Order update request failed.',
                'Integration',
                [
                    new LogContextData('ExceptionMessage', $exception->getMessage()),
                    new LogContextData('ExceptionTrace', $exception->getTraceAsString()),
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
