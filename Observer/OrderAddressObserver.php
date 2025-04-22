<?php

namespace Sequra\Core\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as MagentoAddress;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
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

        /**
         * @var OrderService $orderService
         */
        $orderService = StoreContext::doWithStore((string) $magentoOrder->getStoreId(), function () {
            return ServiceRegister::getService(OrderService::class);
        });

        StoreContext::doWithStore((string) $magentoOrder->getStoreId(), [$orderService, 'updateOrder'], [
            new OrderUpdateData(
                $magentoOrder->getIncrementId(),
                null,
                null,
                $isShippingAddress ? $address : null,
                !$isShippingAddress ? $address : null
            )
        ]);
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
