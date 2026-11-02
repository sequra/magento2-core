<?php

namespace Sequra\Core\Test\Unit\Observer;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreIdProvider;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderUpdateData;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Model\Ui\ConfigProvider;
use Sequra\Core\Observer\OrderCancellationObserver;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

/**
 * Class OrderCancellationObserverTest
 *
 * When a SeQura order is cancelled in Magento the cancellation has to reach SeQura, unless the order
 * was in payment review, where SeQura still has to decide and the cancellation is refused.
 *
 * The observer used to act only on Magento\Sales\Model\Order\Interceptor, so on stores where a module
 * declares a preference for Magento\Sales\Model\Order it never ran. The orders below are mocks of the
 * base class, which are not that interceptor either.
 */
class OrderCancellationObserverTest extends TestCase
{
    /**
     * @var MockObject&OrderService
     */
    private $orderService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderService = $this->createMock(OrderService::class);
        $orderService = $this->orderService;
        ServiceRegister::registerService(OrderService::class, static function () use ($orderService) {
            return $orderService;
        });
        ServiceRegister::registerService(StoreIdProvider::class, static function () {
            return new StoreIdProvider();
        });
        $this->silenceLogger();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Logger::resetInstance();
    }

    /**
     * An order cancelled from any status other than payment review is cancelled in SeQura with empty carts.
     *
     * @return void
     */
    public function testSendsTheCancellationToSequra(): void
    {
        $this->orderService->expects($this->once())
            ->method('updateOrder')
            ->with($this->callback(static function (OrderUpdateData $data) {
                return $data->getOrderShopReference() === '100000001'
                    && $data->getShippedCart() !== null && $data->getShippedCart()->getItems() === []
                    && $data->getUnshippedCart() !== null && $data->getUnshippedCart()->getItems() === [];
            }));

        $this->observer(Order::STATE_PENDING_PAYMENT)->execute($this->event($this->cancelledOrder()));
    }

    /**
     * An order cancelled while SeQura has it in payment review is refused and nothing is sent.
     *
     * @return void
     */
    public function testRefusesToCancelAnOrderInPaymentReview(): void
    {
        $this->orderService->expects($this->never())->method('updateOrder');

        $this->expectException(LocalizedException::class);

        $this->observer(Order::STATE_PAYMENT_REVIEW)->execute($this->event($this->cancelledOrder()));
    }

    /**
     * Saving an order that was already cancelled (a comment, an ERP sync) does not send it again.
     *
     * @return void
     */
    public function testIgnoresAnOrderThatWasAlreadyCancelled(): void
    {
        $this->orderService->expects($this->never())->method('updateOrder');

        $this->observer(Order::STATE_CANCELED)->execute($this->event($this->cancelledOrder()));
    }

    /**
     * Orders paid with another method are left alone.
     *
     * @return void
     */
    public function testIgnoresOtherPaymentMethods(): void
    {
        $this->orderService->expects($this->never())->method('updateOrder');

        $this->observer(Order::STATE_PENDING_PAYMENT)
            ->execute($this->event($this->cancelledOrder('checkmo')));
    }

    /**
     * @param string $previousStatus Status stored in sales_order before this save.
     *
     * @return OrderCancellationObserver
     */
    private function observer(string $previousStatus): OrderCancellationObserver
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn($previousStatus);

        $resource = $this->createMock(OrderResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('sales_order');
        $resource->method('getIdFieldName')->willReturn('entity_id');

        $translationProvider = $this->createMock(SeQuraTranslationProvider::class);
        $translationProvider->method('translate')->willReturn(new Phrase('sequra.error.cannotCancel'));

        return new OrderCancellationObserver($translationProvider, $resource);
    }

    /**
     * @param string $paymentMethod
     *
     * @return MockObject&Order
     */
    private function cancelledOrder(string $paymentMethod = ConfigProvider::CODE): Order
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStatus', 'getId', 'getPayment', 'getStoreId', 'getIncrementId', 'getOrderCurrencyCode'])
            ->getMock();
        $order->method('getStatus')->willReturn(Order::STATE_CANCELED);
        $order->method('getId')->willReturn('42');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');

        return $order;
    }

    /**
     * @param Order $order
     *
     * @return Observer
     */
    private function event(Order $order): Observer
    {
        return new Observer(['order' => $order]);
    }

    /**
     * Replaces the integration-core logger, which needs the shop logger and configuration services.
     *
     * @return void
     */
    private function silenceLogger(): void
    {
        $logger = new class extends Logger {
            public function __construct()
            {
            }

            protected function logMessage(int $level, string $message, string $component, array $context = []): void
            {
            }
        };

        $instance = new ReflectionProperty(Logger::class, 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, $logger);
    }
}
