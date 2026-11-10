<?php

namespace Sequra\Core\Test\Unit\Model\Ui;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class ConfigProviderPaymentMethodTest
 *
 * That the checkout config is empty, without reading the store, while the payment method is inactive.
 */
class ConfigProviderPaymentMethodTest extends TestCase
{
    /**
     * With the payment method inactive, the config is empty and the store is not read.
     *
     * @return void
     */
    public function testConfigIsEmptyWhenThePaymentMethodIsInactive(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $status = $this->createStub(PaymentMethodStatus::class);
        $status->method('isActive')->willReturn(false);

        $provider = new ConfigProvider($storeManager, $this->createStub(UrlInterface::class), $status);

        $this->assertSame([], $provider->getConfig());
    }
}
