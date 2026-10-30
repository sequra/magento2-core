<?php

namespace Sequra\Core\Test\Unit\Model\Checkout;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Checkout\OrderReviewResolver;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class OrderReviewResolverTest
 *
 * That only a seQura order on hold for review is reported as in review.
 */
class OrderReviewResolverTest extends TestCase
{
    /**
     * A seQura order in payment review is on hold.
     *
     * @return void
     */
    public function testASequraOrderInPaymentReviewIsInReview(): void
    {
        $order = $this->order(ConfigProvider::CODE, Order::STATE_PAYMENT_REVIEW);

        $this->assertTrue((new OrderReviewResolver())->isInReview($order));
    }

    /**
     * An approved seQura order is not on hold.
     *
     * @return void
     */
    public function testAnApprovedSequraOrderIsNotInReview(): void
    {
        $order = $this->order(ConfigProvider::CODE, Order::STATE_PENDING_PAYMENT);

        $this->assertFalse((new OrderReviewResolver())->isInReview($order));
    }

    /**
     * A cancelled seQura order is not on hold.
     *
     * @return void
     */
    public function testACancelledSequraOrderIsNotInReview(): void
    {
        $order = $this->order(ConfigProvider::CODE, Order::STATE_CANCELED);

        $this->assertFalse((new OrderReviewResolver())->isInReview($order));
    }

    /**
     * An order of another payment method in payment review is none of seQura's business.
     *
     * @return void
     */
    public function testAnOrderOfAnotherPaymentMethodIsNotInReview(): void
    {
        $order = $this->order('checkmo', Order::STATE_PAYMENT_REVIEW);

        $this->assertFalse((new OrderReviewResolver())->isInReview($order));
    }

    /**
     * An order without a payment is not on hold.
     *
     * @return void
     */
    public function testAnOrderWithoutPaymentIsNotInReview(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getPayment')->willReturn(null);
        $order->method('getStatus')->willReturn(Order::STATE_PAYMENT_REVIEW);

        $this->assertFalse((new OrderReviewResolver())->isInReview($order));
    }

    /**
     * The empty order the checkout session returns when it holds no order is not on hold.
     *
     * @return void
     */
    public function testAnUnsavedOrderIsNotInReview(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn(null);

        $this->assertFalse((new OrderReviewResolver())->isInReview($order));
    }

    /**
     * No order at all is not on hold.
     *
     * @return void
     */
    public function testNoOrderIsNotInReview(): void
    {
        $this->assertFalse((new OrderReviewResolver())->isInReview(null));
    }

    /**
     * A saved order paid with the given method and in the given status.
     *
     * @param string $paymentMethod
     * @param string $status
     *
     * @return OrderInterface
     */
    private function order(string $paymentMethod, string $status): OrderInterface
    {
        $payment = $this->createStub(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStatus')->willReturn($status);

        return $order;
    }
}
