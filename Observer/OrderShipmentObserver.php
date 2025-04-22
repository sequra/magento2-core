<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Model\Ui\ConfigProvider;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Sequra\Core\Services\BusinessLogic\Utility\TransformEntityService;

/**
 * Class OrderShipmentObserver
 *
 * @package Sequra\Core\Observer
 */
class OrderShipmentObserver implements ObserverInterface
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
     * @param SeQuraTranslationProvider $translationProvider
     * @param TransformEntityService $transformService
     */
    public function __construct(SeQuraTranslationProvider $translationProvider, TransformEntityService $transformService)
    {
        $this->translationProvider = $translationProvider;
        $this->transformService = $transformService;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /**
         * @var MagentoShipment $shipmentData
         */
        $shipmentData = $observer->getData('data_object');

        try {
            $this->handleShipment($shipmentData);
        } catch (Exception $e) {
            $this->handleShipmentError($e);
        }
    }

    /**
     * Handles the shipment event.
     *
     * @param MagentoShipment $shipmentData
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleShipment(MagentoShipment $shipmentData): void
    {
        $orderData = $shipmentData->getOrder();
        $payment = $orderData->getPayment();

        if (!$payment || $payment->getMethod() !== ConfigProvider::CODE) {
            return;
        }

        if ($orderData->getStatus() === Order::STATE_PAYMENT_REVIEW) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.cannotShip'));
        }

        $unshippedCart = $this->transformService->transformOrderCartToSeQuraCart($orderData, false);
        $shippedCart = $this->transformService->transformOrderCartToSeQuraCart($orderData, true);

        $storeId = (string) $orderData->getStoreId();
        /**
         * @var OrderService $orderService
         */
        $orderService = StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(OrderService::class);
        });

        StoreContext::doWithStore($storeId, [$orderService, 'updateOrder'], [
            new OrderUpdateData(
                $orderData->getIncrementId(),
                $shippedCart,
                $unshippedCart,
                null,
                null
            )
        ]);
    }

    /**
     * Handles the order shipment errors.
     *
     * @param Exception $e
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function handleShipmentError(Exception $e): void
    {
        Logger::logError('Order synchronization for shipment failed. ' . $e->getMessage(), 'Integration');

        if ($e instanceof LocalizedException) {
            throw $e;
        }
    }
}
