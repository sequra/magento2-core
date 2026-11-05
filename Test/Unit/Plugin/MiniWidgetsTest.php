<?php

namespace Sequra\Core\Test\Unit\Plugin;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Escaper;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\Render\Amount;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Plugin\MiniWidgets;

/**
 * Class MiniWidgetsTest
 *
 * That the listing mini widget reads neither the store nor the price while the payment method is inactive.
 */
class MiniWidgetsTest extends TestCase
{
    /**
     * With the payment method inactive, the price HTML comes back untouched without reading the store or the price.
     *
     * @return void
     */
    public function testLeavesThePriceUntouchedWhenThePaymentMethodIsInactive(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $amount = $this->createMock(Amount::class);
        $amount->method('getData')->willReturnCallback(
            fn ($key) => ['zone' => 'item_list', 'price_type' => 'finalPrice'][$key] ?? null
        );
        $amount->expects($this->never())->method('getPrice');

        $status = $this->createStub(PaymentMethodStatus::class);
        $status->method('isActive')->willReturn(false);

        $plugin = new MiniWidgets(
            $storeManager,
            $this->createStub(ScopeResolverInterface::class),
            $this->createStub(ResolverInterface::class),
            $this->createStub(Session::class),
            $this->createStub(Request::class),
            $this->createStub(Escaper::class),
            $status
        );

        $this->assertSame('<span>9,99 €</span>', $plugin->afterToHtml($amount, '<span>9,99 €</span>'));
    }
}
