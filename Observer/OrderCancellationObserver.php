<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Interceptor as MagentoOrder;
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
     * @param SeQuraTranslationProvider $translationProvider
     * @param OrderUpdater $orderUpdater
     */
    public function __construct(SeQuraTranslationProvider $translationProvider, OrderUpdater $orderUpdater)
    {
        $this->translationProvider = $translationProvider;
        $this->orderUpdater = $orderUpdater;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $orderData = $observer->getData('order');
        if (WebhookController::isWebhookProcessing() || ! $orderData instanceof MagentoOrder ||
            $orderData->getStatus() !== Order::STATE_CANCELED ||
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
     * @param MagentoOrder $orderData
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleCancellation(MagentoOrder $orderData): void
    {
        $statusHistory = $orderData->getAllStatusHistory();
        if (count($statusHistory) >= 2
            && $statusHistory[count($statusHistory) - 2]->getStatus() === Order::STATE_PAYMENT_REVIEW
        ) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.cannotCancel'));
        }

        $this->orderUpdater->update(
            (string)$orderData->getStoreId(),
            new OrderUpdateRequest(
                $orderData->getIncrementId(),
                new SeQuraCart($orderData->getOrderCurrencyCode() ?? ''),
                new SeQuraCart($orderData->getOrderCurrencyCode() ?? '')
            )
        );
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
