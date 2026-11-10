<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator;

/**
 * Class AvailabilityEvaluatorPaymentMethodTest
 *
 * That Express Checkout is hidden, without asking SeQura, while the payment method is inactive.
 */
class AvailabilityEvaluatorPaymentMethodTest extends TestCase
{
    /**
     * With the payment method inactive, the state is hidden and the status is read for the evaluated store.
     *
     * @return void
     */
    public function testIsHiddenWhenThePaymentMethodIsInactive(): void
    {
        $status = $this->createMock(PaymentMethodStatus::class);
        $status->expects($this->once())->method('isActive')->with('3')->willReturn(false);

        $result = (new AvailabilityEvaluator($status))->evaluate('3', 'product', 'ES', 'EUR', '', ['1'], []);

        $this->assertSame(['state' => AvailabilityEvaluator::STATE_HIDDEN, 'buttonStyle' => null], $result);
    }
}
