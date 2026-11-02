<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
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
     * @param SeQuraTranslationProvider $translationProvider
     */
    public function __construct(SeQuraTranslationProvider $translationProvider)
    {
        $this->translationProvider = $translationProvider;
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
        if (!$orderData->getId()) {
            return null;
        }

        $resource = $orderData->getResource();
        $connection = $resource->getConnection();
        $status = $connection->fetchOne(
            $connection->select()
                ->from($resource->getMainTable(), ['status'])
                ->where($resource->getIdFieldName() . ' = ?', $orderData->getId())
        );

        return $status === false ? null : (string)$status;
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
