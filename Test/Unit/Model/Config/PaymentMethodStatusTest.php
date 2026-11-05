<?php

namespace Sequra\Core\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Config\PaymentMethodStatus;

/**
 * Class PaymentMethodStatusTest
 *
 * That the status is the store-scoped active flag of the payment method.
 */
class PaymentMethodStatusTest extends TestCase
{
    /**
     * The flag of the given store is read.
     *
     * @return void
     */
    public function testReadsTheStoreScopedActiveFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('payment/sequra_payment/active', ScopeInterface::SCOPE_STORE, '3')
            ->willReturn(true);

        $this->assertTrue((new PaymentMethodStatus($scopeConfig))->isActive('3'));
    }

    /**
     * A method whose flag is off reads as inactive.
     *
     * @return void
     */
    public function testIsInactiveWhenTheFlagIsOff(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse((new PaymentMethodStatus($scopeConfig))->isActive());
    }
}
