<?php

namespace Sequra\Core\Test\Unit\Plugin;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableProduct;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Plugin\Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;

/**
 * Class ConfigurablePaymentMethodTest
 *
 * That the configurable JSON keeps its shape, without reading SeQura's settings, while the payment method is inactive.
 */
class ConfigurablePaymentMethodTest extends TestCase
{
    /**
     * With the payment method inactive, skus and excludedProducts are still present and the store is not read.
     *
     * @return void
     */
    public function testKeepsSkusAndExcludedProductsWhenThePaymentMethodIsInactive(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $status = $this->createStub(PaymentMethodStatus::class);
        $status->method('isActive')->willReturn(false);

        $child = $this->createStub(Product::class);
        $child->method('getId')->willReturn('7');
        $child->method('getSku')->willReturn('SKU-7');
        $subject = $this->createStub(ConfigurableProduct::class);
        $subject->method('getAllowProducts')->willReturn([$child]);

        $json = (new Configurable($storeManager, $status))->afterGetJsonConfig($subject, '{"productId":"5"}');

        $this->assertSame(
            ['productId' => '5', 'skus' => ['7' => 'SKU-7'], 'excludedProducts' => []],
            json_decode($json, true)
        );
    }
}
