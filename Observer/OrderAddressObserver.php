<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as MagentoAddress;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Model\Api\OrderUpdater;
use Sequra\Core\Model\Ui\ConfigProvider;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Sequra\Core\Services\BusinessLogic\Utility\TransformEntityService;

class OrderAddressObserver implements ObserverInterface
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
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * Constructor
     *
     * @param SeQuraTranslationProvider $translationProvider
     * @param TransformEntityService $transformService
     * @param OrderUpdater $orderUpdater
     */
    public function __construct(
        SeQuraTranslationProvider $translationProvider,
        TransformEntityService $transformService,
        OrderUpdater $orderUpdater
    ) {
        $this->translationProvider = $translationProvider;
        $this->transformService = $transformService;
        $this->orderUpdater = $orderUpdater;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        if (WebhookController::isWebhookProcessing()) {
            return;
        }

        /**
         * @var MagentoAddress $addressData
         */
        $addressData = $observer->getData('data_object');

        try {
            $this->handleAddressUpdate($addressData);
        } catch (Exception $e) {
            $this->handleAddressUpdateError($e);
        }
    }

    /**
     * Handles address update.
     *
     * @param MagentoAddress $magentoAddress
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleAddressUpdate(MagentoAddress $magentoAddress): void
    {
        $magentoOrder = $magentoAddress->getOrder();
        $payment = $magentoOrder->getPayment();

        if (!$payment || $payment->getMethod() !== ConfigProvider::CODE) {
            return;
        }

        if ($magentoOrder->getStatus() === Order::STATE_PAYMENT_REVIEW) {
            throw new LocalizedException($this->translationProvider->translate('sequra.error.cannotUpdate'));
        }

        $isShippingAddress = $magentoAddress->getAddressType() === 'shipping';
        $address = $this->transformService->transformAddressToSeQuraOrderAddress($magentoAddress);

        $this->orderUpdater->update(
            (string) $magentoOrder->getStoreId(),
            new OrderUpdateRequest(
                $magentoOrder->getIncrementId(),
                null,
                null,
                $isShippingAddress ? $address : null,
                !$isShippingAddress ? $address : null
            )
        );
    }

    /**
     * Handles the update address errors.
     *
     * @param Exception $e
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function handleAddressUpdateError(Exception $e): void
    {
        Logger::logError('Order synchronization for address update failed. ' . $e->getMessage(), 'Integration');

        if ($e instanceof LocalizedException) {
            throw $e;
        }
    }
}
