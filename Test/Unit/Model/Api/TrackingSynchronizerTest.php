<?php

namespace Sequra\Core\Test\Unit\Model\Api;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderPaymentSearchResultInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\AdminAPI\OrderManagement\Requests\OrderUpdateRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Tracking\TrackingPostal;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\Infrastructure\Configuration\Configuration;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\Logger\LoggerConfiguration;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\Utility\TimeProvider;
use Sequra\Core\Model\Api\Builders\TrackingBuilder;
use Sequra\Core\Model\Api\Exception\OrderUpdateFailedException;
use Sequra\Core\Model\Api\OrderUpdater;
use Sequra\Core\Model\Api\TrackingSynchronizer;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class TrackingSynchronizerTest
 *
 * That only seQura orders get their tracking list sent, the list alone and in full, that other orders are not
 * even loaded, and that a failed update never reaches the shipment being saved.
 */
class TrackingSynchronizerTest extends TestCase
{
    /**
     * @var (MockObject&OrderUpdater)|(Stub&OrderUpdater)
     */
    private $orderUpdater;

    /**
     * @var (MockObject&TrackingBuilder)|(Stub&TrackingBuilder)
     */
    private $trackingBuilder;

    /**
     * @var (MockObject&OrderRepositoryInterface)|(Stub&OrderRepositoryInterface)
     */
    private $orderRepository;

    /**
     * Payment method of order 42 as the payment repository answers it, null for an order without payment.
     *
     * @var string|null
     */
    private $paymentMethod = ConfigProvider::CODE;

    /**
     * @return void
     *
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->orderUpdater = $this->createStub(OrderUpdater::class);
        $this->trackingBuilder = $this->createStub(TrackingBuilder::class);
        $this->orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $this->orderRepository->method('get')->willReturn($this->order());
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Logger::resetInstance();
        LoggerConfiguration::resetInstance();
    }

    /**
     * The tracking list is sent alone, under the order's increment id and store.
     *
     * @return void
     */
    public function testSendsTheTrackingListOfASeQuraOrder(): void
    {
        $tracking = new TrackingPostal('15', 'DHL', 'JD0123');
        $this->trackingBuilder->method('build')->willReturn([$tracking]);

        $this->orderUpdater = $this->createMock(OrderUpdater::class);
        $this->orderUpdater->expects($this->once())
            ->method('update')
            ->with('3', $this->callback(function (OrderUpdateRequest $request) use ($tracking): bool {
                /** @var OrderUpdateData $data */
                $data = $request->transformToDomainModel();

                return $data->getOrderShopReference() === '000000123'
                    && $data->getTrackings() === [$tracking]
                    && $data->getShippedCart() === null
                    && $data->getUnshippedCart() === null
                    && $data->getDeliveryAddress() === null
                    && $data->getInvoiceAddress() === null;
            }))
            ->willReturn($this->createStub(SeQuraOrder::class));

        $this->synchronizer()->synchronize(42);
    }

    /**
     * An order left without tracking records sends an empty list, so seQura clears the ones it has.
     *
     * @return void
     */
    public function testSendsAnEmptyListWhenNoRecordIsLeft(): void
    {
        $this->trackingBuilder->method('build')->willReturn([]);

        $this->orderUpdater = $this->createMock(OrderUpdater::class);
        $this->orderUpdater->expects($this->once())
            ->method('update')
            ->with('3', $this->callback(function (OrderUpdateRequest $request): bool {
                /** @var OrderUpdateData $data */
                $data = $request->transformToDomainModel();

                return $data->getTrackings() === [];
            }))
            ->willReturn($this->createStub(SeQuraOrder::class));

        $this->synchronizer()->synchronize(42);
    }

    /**
     * An order paid with another method is ignored, the trackings are not even built.
     *
     * @return void
     */
    public function testIgnoresAnOrderPaidWithAnotherMethod(): void
    {
        $this->paymentMethod = 'checkmo';
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderRepository->expects($this->never())->method('get');
        $this->trackingBuilder = $this->createMock(TrackingBuilder::class);
        $this->trackingBuilder->expects($this->never())->method('build');
        $this->orderUpdater = $this->createMock(OrderUpdater::class);
        $this->orderUpdater->expects($this->never())->method('update');

        $this->synchronizer()->synchronize(42);
    }

    /**
     * An order without payment is ignored.
     *
     * @return void
     */
    public function testIgnoresAnOrderWithoutPayment(): void
    {
        $this->paymentMethod = null;
        $this->orderUpdater = $this->createMock(OrderUpdater::class);
        $this->orderUpdater->expects($this->never())->method('update');

        $this->synchronizer()->synchronize(42);
    }

    /**
     * An update seQura refuses is logged instead of raised, so the shipment still saves.
     *
     * @return void
     */
    public function testLogsARefusedUpdateInsteadOfRaisingIt(): void
    {
        $this->trackingBuilder->method('build')->willReturn([]);
        $this->orderUpdater->method('update')->willThrowException(
            new OrderUpdateFailedException('Order not found.', 'general.errors.order.notFound', 404)
        );

        $shopLogger = $this->createMock(ShopLoggerAdapter::class);
        $shopLogger->expects($this->once())->method('logMessage');
        $this->registerLogger($shopLogger);

        $this->synchronizer()->synchronize(42);
    }

    /**
     * Builds the synchronizer over the mocked builder, updater and repositories.
     *
     * @return TrackingSynchronizer
     */
    private function synchronizer(): TrackingSynchronizer
    {
        $payments = [];
        if ($this->paymentMethod !== null) {
            $payment = $this->createStub(Payment::class);
            $payment->method('getMethod')->willReturn($this->paymentMethod);
            $payments[] = $payment;
        }

        $searchResult = $this->createStub(OrderPaymentSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn($payments);
        $paymentRepository = $this->createStub(OrderPaymentRepositoryInterface::class);
        $paymentRepository->method('getList')->willReturn($searchResult);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->expects($this->once())->method('addFilter')->with('parent_id', 42)->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($this->createStub(SearchCriteria::class));

        return new TrackingSynchronizer(
            $this->trackingBuilder,
            $this->orderUpdater,
            $this->orderRepository,
            $paymentRepository,
            $searchCriteriaBuilder
        );
    }

    /**
     * Registers the services the integration-core logger writes through.
     *
     * @param ShopLoggerAdapter $shopLogger
     *
     * @return void
     */
    private function registerLogger(ShopLoggerAdapter $shopLogger): void
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getIntegrationName')->willReturn('Magento2');

        ServiceRegister::registerService(ShopLoggerAdapter::CLASS_NAME, static function () use ($shopLogger) {
            return $shopLogger;
        });
        ServiceRegister::registerService(TimeProvider::CLASS_NAME, function () {
            return $this->createStub(TimeProvider::class);
        });
        ServiceRegister::registerService(Configuration::CLASS_NAME, static function () use ($configuration) {
            return $configuration;
        });
    }

    /**
     * Returns order 000000123 of store 3.
     *
     * @return Order
     */
    private function order(): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(3);
        $order->method('getIncrementId')->willReturn('000000123');

        return $order;
    }
}
