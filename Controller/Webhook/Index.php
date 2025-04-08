<?php

namespace Sequra\Core\Controller\Webhook;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Service\InvoiceService;
use SeQura\Core\BusinessLogic\Domain\Order\OrderStates;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\BusinessLogic\Webhook\Exceptions\OrderNotFoundException;
use SeQura\Core\BusinessLogic\WebhookAPI\WebhookAPI;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;

class Index extends Action
{
    /**
     * @var string
     */
    private const PREFIX = 'm_';
    
    /**
     * @var bool
     */
    private static $isWebhookProcessing = false;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;
    
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;
    
    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;
    
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * Constructor for Index controller
     *
     * @param Context $context
     * @param InvoiceService $invoiceService
     * @param InvoiceRepository $invoiceRepository
     * @param TransactionFactory $transactionFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context                  $context,
        InvoiceService           $invoiceService,
        InvoiceRepository        $invoiceRepository,
        TransactionFactory       $transactionFactory,
        SearchCriteriaBuilder    $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->invoiceService = $invoiceService;
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionFactory = $transactionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Execute webhook endpoint
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }

        $payload = $this->getRequest()->getPostValue();
        foreach ($payload as $key => $value) {
            $newKey = $key === 'event' ? 'sq_state' : $this->trimPrefixFromKey($key);
            $modifiedPayload[$newKey] = $value;
        }

        if (empty($modifiedPayload['storeId'])) {
            return;
        }

        self::setIsWebhookProcessing(true);
        $response = WebhookAPI::webhookHandler($modifiedPayload['storeId'])->handleRequest($modifiedPayload);
        self::setIsWebhookProcessing(false);

        if ($response->isSuccessful()) {
            if ($modifiedPayload['sq_state'] === OrderStates::STATE_APPROVED) {
                $this->createInvoiceForOrder($modifiedPayload['order_ref']);
            }

            return;
        }

        $error = $response->toArray();
        $code = (isset($error['errorCode']) && $error['errorCode'] === 409) ? 409 : 410;

        $this->getResponse()->setHttpResponseCode($code);
        $this->getResponse()->setBody($response->toArray()['errorMessage']);
        $this->getResponse()->sendResponse();
    }

    // TODO: Fix this, static method cannot be intercepted and its use is discouraged
    // phpcs:disable Magento2.Functions.StaticFunction.StaticFunction
    /**
     * Is webhook processing
     *
     * @return bool
     */
    public static function isWebhookProcessing(): bool
    {
        return self::$isWebhookProcessing;
    }

    /**
     * Sets the static variable value
     *
     * @param bool $isWebhookProcessing
     *
     * @return void
     */
    private static function setIsWebhookProcessing(bool $isWebhookProcessing): void
    {
        self::$isWebhookProcessing = $isWebhookProcessing;
    }
    // TODO: Fix this, static method cannot be intercepted and its use is discouraged
    // phpcs:enable Magento2.Functions.StaticFunction.StaticFunction

    /**
     * Creates an invoice for the order.
     *
     * @param string $orderRef
     *
     * @return void
     */
    private function createInvoiceForOrder(string $orderRef): void
    {
        $sequraOrder = $this->getSequraOrderRepository()->getByOrderReference($orderRef);

        if (!$sequraOrder) {
            Logger::logError(
                'Invoice not created because the order with the reference ' . $orderRef . ' was not found.',
                'Integration'
            );
        }

        try {
            $order = $this->getMagentoOrder($sequraOrder->getOrderRef1());

            $payment = $order->getPayment();
            $payment->setTransactionId($sequraOrder->getReference());
            $payment->setParentTransactionId($sequraOrder->getReference());
            $payment->setShouldCloseParentTransaction(true);
            $payment->setIsTransactionClosed(0);

            $transaction = $this->transactionFactory->create();

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();

            $transaction->addObject($invoice);
            $transaction->save();

            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            $this->orderRepository->save($order);
        } catch (Exception $e) {
            Logger::logError('Invoice for order not created. ' . $e->getMessage(), 'Integration');
        }
    }

    /**
     * Gets the magento order from the repository for a given incremented order id.
     *
     * @param string $incrementedId
     *
     * @return Order
     *
     * @throws OrderNotFoundException
     */
    private function getMagentoOrder(string $incrementedId): Order
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementedId)
            ->create();

        $searchResult = $this->orderRepository->getList($searchCriteria);
        if (!$searchResult) {
            throw new OrderNotFoundException('Magento order with the incremented id ' . $incrementedId . ' not found.');
        }

        $orderList = $searchResult->getItems();

        return array_pop($orderList);
    }

    /**
     * Trims a prefix from a given key.
     *
     * @param string $key
     *
     * @return string
     */
    private function trimPrefixFromKey(string $key): string
    {
        return str_starts_with($key, self::PREFIX) ? substr($key, strlen(self::PREFIX)) : $key;
    }

    /**
     * Gets an instance of SeQuraOrderRepository
     *
     * @return SeQuraOrderRepositoryInterface
     */
    private function getSequraOrderRepository(): SeQuraOrderRepositoryInterface
    {
        return ServiceRegister::getService(SeQuraOrderRepositoryInterface::class);
    }
}
