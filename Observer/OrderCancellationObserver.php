<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Cart as SeQuraCart;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Model\Ui\ConfigProvider;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

class OrderCancellationObserver implements ObserverInterface
{
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * @param SeQuraTranslationProvider $translationProvider
     * @param OrderResource $orderResource
     */
    public function __construct(SeQuraTranslationProvider $translationProvider, OrderResource $orderResource)
    {
        $this->translationProvider = $translationProvider;
        $this->orderResource = $orderResource;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $orderData = $observer->getData('order');
        if (WebhookController::isWebhookProcessing() || ! $orderData instanceof Order ||
            $orderData->getStatus() !== Order::STATE_CANCELED ||
            $this->getPreviousStatus($orderData) === Order::STATE_CANCELED ||
            !$orderData->getPayment() ||
            $orderData->getPayment()->getMethod() !== ConfigProvider::CODE) {
            return;
        }

        try {
            $this->handleCancellation($orderData);
        } catch (Exception $e) {
            $this->handleCancellationError($e);
        }
    }

    /**
     * Handles order cancellation.
     *
     * @param Order $orderData
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleCancellation(Order $orderData): void
    {
        if ($this->getPreviousStatus($orderData) === Order::STATE_PAYMENT_REVIEW) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.cannotCancel'));
        }

        /**
         * @var OrderService $orderService
         */
        $orderService = StoreContext::doWithStore((string)$orderData->getStoreId(), function () {
            return ServiceRegister::getService(OrderService::class);
        });

        StoreContext::doWithStore((string)$orderData->getStoreId(), [$orderService, 'updateOrder'], [
            new OrderUpdateData(
                $orderData->getIncrementId(),
                new SeQuraCart($orderData->getOrderCurrencyCode() ?? ''),
                new SeQuraCart($orderData->getOrderCurrencyCode() ?? ''),
                null,
                null
            )
        ]);
    }

    /**
     * Returns the status stored for the order before this save.
     *
     * Reads it from the database instead of the original data: orders saved through the REST API have no original
     * data, and the original data is not refreshed when the same order is saved twice in one request.
     *
     * @param Order $orderData
     *
     * @return string|null
     */
    private function getPreviousStatus(Order $orderData): ?string
    {
        $orderId = $orderData->getId();
        if (!is_numeric($orderId)) {
            return null;
        }

        $connection = $this->orderResource->getConnection();
        if (!$connection) {
            return null;
        }

        $status = $connection->fetchOne(
            $connection->select()
                ->from($this->orderResource->getMainTable(), ['status'])
                ->where($this->orderResource->getIdFieldName() . ' = ?', (int)$orderId)
        );

        return is_string($status) ? $status : null;
    }

    /**
     * Handles the order cancellation errors.
     *
     * @param Exception $e
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function handleCancellationError(Exception $e): void
    {
        Logger::logError('Order synchronization for cancellation failed. ' . $e->getMessage(), 'Integration');

        if ($e instanceof LocalizedException) {
            throw $e;
        }
    }
}
