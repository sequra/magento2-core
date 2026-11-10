<?php

namespace Sequra\Core\Test\Unit\Model\Config;

use Magento\Payment\Gateway\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Config\PaymentMethodStatus;

/**
 * Class PaymentMethodStatusTest
 *
 * That the status is the active flag of the payment method's gateway config.
 */
class PaymentMethodStatusTest extends TestCase
{
    /**
     * The flag of the given store is read from the gateway config.
     *
     * @return void
     */
    public function testReadsTheActiveFlagOfTheGivenStore(): void
    {
        $gatewayConfig = $this->createMock(ConfigInterface::class);
        $gatewayConfig->expects($this->once())
            ->method('getValue')
            ->with('active', 3)
            ->willReturn('1');

        $this->assertTrue((new PaymentMethodStatus($gatewayConfig))->isActive('3'));
    }

    /**
     * A method whose flag is off reads as inactive.
     *
     * @return void
     */
    public function testIsInactiveWhenTheFlagIsOff(): void
    {
        $gatewayConfig = $this->createStub(ConfigInterface::class);
        $gatewayConfig->method('getValue')->willReturn('0');

        $this->assertFalse((new PaymentMethodStatus($gatewayConfig))->isActive());
    }
}
