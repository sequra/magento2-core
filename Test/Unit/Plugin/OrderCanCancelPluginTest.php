<?php

namespace Sequra\Core\Test\Unit\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sequra\Core\Controller\Webhook\Index as WebhookController;
use Sequra\Core\Model\Ui\ConfigProvider;
use Sequra\Core\Plugin\OrderCanCancelPlugin;

/**
 * Class OrderCanCancelPluginTest
 *
 * A SeQura order in payment review cannot be cancelled from Magento until SeQura decides, while the
 * cancellation SeQura sends through its webhook still goes through.
 */
class OrderCanCancelPluginTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->setWebhookProcessing(false);
    }

    /**
     * A SeQura order in payment review cannot be cancelled.
     *
     * @return void
     */
    public function testRefusesASequraOrderInPaymentReview(): void
    {
        $this->assertFalse(
            (new OrderCanCancelPlugin())->afterCanCancel($this->order(Order::STATE_PAYMENT_REVIEW), true)
        );
    }

    /**
     * Orders in payment review paid with another method keep the original result.
     *
     * @return void
     */
    public function testKeepsTheResultForOtherPaymentMethods(): void
    {
        $this->assertTrue(
            (new OrderCanCancelPlugin())->afterCanCancel($this->order(Order::STATE_PAYMENT_REVIEW, 'checkmo'), true)
        );
    }

    /**
     * SeQura orders in any other status keep the original result, without loading the payment.
     *
     * @return void
     */
    public function testKeepsTheResultForOtherStatuses(): void
    {
        $order = $this->order('pending_payment');
        $order->expects($this->never())->method('getPayment');

        $this->assertTrue((new OrderCanCancelPlugin())->afterCanCancel($order, true));
    }

    /**
     * The cancellation SeQura sends for an order in review is let through.
     *
     * @return void
     */
    public function testKeepsTheResultWhileProcessingAWebhook(): void
    {
        $this->setWebhookProcessing(true);

        $this->assertTrue(
            (new OrderCanCancelPlugin())->afterCanCancel($this->order(Order::STATE_PAYMENT_REVIEW), true)
        );
    }

    /**
     * An order Magento already refuses to cancel stays refused.
     *
     * @return void
     */
    public function testKeepsAFalseResult(): void
    {
        $order = $this->order(Order::STATE_PAYMENT_REVIEW);
        $order->expects($this->never())->method('getStatus');

        $this->assertFalse((new OrderCanCancelPlugin())->afterCanCancel($order, false));
    }

    /**
     * @param string $status
     * @param string $paymentMethod
     *
     * @return MockObject&Order
     */
    private function order(string $status, string $paymentMethod = ConfigProvider::CODE): Order
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStatus', 'getPayment'])
            ->getMock();
        $order->method('getStatus')->willReturn($status);
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }

    /**
     * @param bool $processing
     *
     * @return void
     */
    private function setWebhookProcessing(bool $processing): void
    {
        $property = new ReflectionProperty(WebhookController::class, 'isWebhookProcessing');
        $property->setAccessible(true);
        $property->setValue(null, $processing);
    }
}
