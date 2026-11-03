<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Data\Form\FormKey;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\Store;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\CartSummaryFormDecorator;
use Sequra\Core\Model\QuoteEmailResolver;

class CartUpdateRelayTest extends TestCase
{
    private const FORM = '<div data-base-url="https://sandbox.sequracdn.com/orders/abc123/form"></div>';

    /**
     * @return void
     */
    public function testEchoesTheCartUpdateIdOnEveryAnswer(): void
    {
        $script = $this->script();

        $this->assertStringContainsString("data.cartUpdateId = message.cartUpdateId;", $script);
        $this->assertStringContainsString("sendFailure(response.status, message.cartUpdateId);", $script);
        $this->assertStringContainsString("sendFailure(0, message.cartUpdateId);", $script);
        $this->assertStringContainsString(
            "{ type: 'cartUpdateFailed', status: status, cartUpdateId: cartUpdateId }",
            $script
        );
    }

    /**
     * @return void
     */
    public function testRelaysOneUpdateAtATime(): void
    {
        $script = $this->script();

        $this->assertStringContainsString("var cartUpdateQueue = Promise.resolve();", $script);
        $this->assertMatchesRegularExpression(
            '/cartUpdateQueue = cartUpdateQueue\s*\.then\(function \(\) \{[\s\S]*?return fetch\(updateUrl/',
            $script
        );
        $this->assertSame(1, substr_count($script, 'fetch('));
    }

    /**
     * @return void
     */
    public function testAbortsAStalledUpdateAfterThirtySeconds(): void
    {
        $script = $this->script();

        $this->assertStringContainsString(
            "var controller = typeof AbortController === 'function' ? new AbortController() : null;",
            $script
        );
        $this->assertStringContainsString("setTimeout(function () { controller.abort(); }, 30000)", $script);
        $this->assertStringContainsString("signal: controller ? controller.signal : undefined", $script);
        $this->assertSame(2, substr_count($script, 'clearTimeout(timer);'));
    }

    /**
     * @return string
     */
    private function script(): string
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllShippingRates', 'getName', 'getStreet', 'getRegionId'])
            ->getMock();
        $address->method('getAllShippingRates')->willReturn([]);
        $address->method('getName')->willReturn('Marina Garcia');
        $address->method('getStreet')->willReturn(['Carrer de Pallars 128']);
        $address->method('getRegionId')->willReturn('155');
        $address->setData('country_id', 'ES');

        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('Tienda');
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/media/');
        $store->method('getUrl')->willReturn('https://shop.example.com/sequra/expresscheckout/cartupdate');

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getStore', 'getAllVisibleItems'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getStore')->willReturn($store);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $quote->setData('grand_total', 43.0);
        $quote->setData('quote_currency_code', 'EUR');

        $emailResolver = $this->createMock(QuoteEmailResolver::class);
        $emailResolver->method('resolve')->willReturn('marina@example.com');
        $directoryHelper = $this->createMock(DirectoryHelper::class);
        $directoryHelper->method('isRegionRequired')->willReturn(false);
        $logoPathResolver = $this->createMock(LogoPathResolver::class);
        $logoPathResolver->method('getPath')->willReturn('');
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('formkey');

        $decorator = new CartSummaryFormDecorator(
            $this->createMock(ImageHelper::class),
            $this->createMock(ItemResolverInterface::class),
            $this->createMock(ShippingMethodConverter::class),
            $logoPathResolver,
            $formKey,
            $emailResolver,
            $directoryHelper
        );

        return $decorator->decorate(self::FORM, $quote, 'token');
    }
}
