<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Cart as SeQuraCart;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Model\Api\OrderUpdater;
use Sequra\Core\Model\Ui\ConfigProvider;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

class OrderCancellationObserver implements ObserverInterface
{
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * @param SeQuraTranslationProvider $translationProvider
     * @param OrderUpdater $orderUpdater
     * @param OrderResource $orderResource
     */
    public function __construct(
        SeQuraTranslationProvider $translationProvider,
        OrderUpdater $orderUpdater,
        OrderResource $orderResource
    ) {
        $this->translationProvider = $translationProvider;
        $this->orderUpdater = $orderUpdater;
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
            !$orderData->getPayment() ||
            $orderData->getPayment()->getMethod() !== ConfigProvider::CODE) {
            return;
        }

        $previousStatus = $this->getPreviousStatus($orderData);
        if ($previousStatus === Order::STATE_CANCELED) {
            return;
        }

        try {
            $this->handleCancellation($orderData, $previousStatus);
        } catch (Exception $e) {
            $this->handleCancellationError($e);
        }
    }

    /**
     * Handles order cancellation.
     *
     * @param Order $orderData
     * @param string|null $previousStatus Status stored for the order before this save.
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleCancellation(Order $orderData, ?string $previousStatus): void
    {
        if ($previousStatus === Order::STATE_PAYMENT_REVIEW) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.cannotCancel'));
        }

        $currency = $orderData->getOrderCurrencyCode() ?? '';
        $this->orderUpdater->update(
            (string)$orderData->getStoreId(),
            new OrderUpdateRequest(
                $orderData->getIncrementId(),
                new SeQuraCart($currency),
                new SeQuraCart($currency)
            )
        );
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
