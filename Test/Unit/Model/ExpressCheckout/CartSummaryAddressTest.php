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

/**
 * Class CartSummaryAddressTest
 *
 * That the address survives the round trip between the quote and the express address sheet.
 *
 * The sheet has two street inputs and {@see \Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver::applyAddress}
 * stores them as two street lines, so the payload has to hand both back. Joining them into
 * `addressLine1` made every save lossy: the sheet reopened with line 2 blank and both lines in
 * line 1, and the next save persisted that single line as the address the Magento order and the
 * shipping label carry.
 */
class CartSummaryAddressTest extends TestCase
{
    /**
     * A form snippet shaped like the one a solicit returns.
     */
    private const FORM = '<div data-base-url="https://sandbox.sequracdn.com/orders/abc123/form"></div>';

    /**
     * A two-line street comes back as two fields, not as one joined line.
     *
     * @return void
     */
    public function testSendsBothStreetLinesSeparately(): void
    {
        $address = $this->addressOf(['Carrer de Pallars 128', 'Esc B, 4o 2a']);

        $this->assertSame('Carrer de Pallars 128', $address['addressLine1']);
        $this->assertSame('Esc B, 4o 2a', $address['addressLine2']);
    }

    /**
     * A one-line street leaves the second field empty rather than absent, so the sheet renders a
     * blank input instead of an undefined one.
     *
     * @return void
     */
    public function testSendsAnEmptySecondLineForAOneLineStreet(): void
    {
        $address = $this->addressOf(['Carrer de Pallars 128']);

        $this->assertSame('Carrer de Pallars 128', $address['addressLine1']);
        $this->assertArrayHasKey('addressLine2', $address);
        $this->assertSame('', $address['addressLine2']);
    }

    /**
     * Magento allows more street lines than the sheet has inputs; everything past the first is
     * folded into line 2 rather than dropped.
     *
     * @return void
     */
    public function testFoldsExtraStreetLinesIntoTheSecondField(): void
    {
        $address = $this->addressOf(['Carrer de Pallars 128', 'Esc B', '4o 2a']);

        $this->assertSame('Carrer de Pallars 128', $address['addressLine1']);
        $this->assertSame('Esc B, 4o 2a', $address['addressLine2']);
    }

    /**
     * Blank lines Magento pads the street with do not become an empty leading field.
     *
     * @return void
     */
    public function testIgnoresBlankStoredLines(): void
    {
        $address = $this->addressOf(['', 'Carrer de Pallars 128', '   ']);

        $this->assertSame('Carrer de Pallars 128', $address['addressLine1']);
        $this->assertSame('', $address['addressLine2']);
    }

    /**
     * Builds the payload for a quote carrying the given street and returns its shipping address.
     *
     * @param string[] $street Street lines as the quote address stores them.
     *
     * @return array<string, string>
     */
    private function addressOf(array $street): array
    {
        $shippingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllShippingRates', 'getName', 'getStreet', 'getRegionId'])
            ->getMock();
        $shippingAddress->method('getAllShippingRates')->willReturn([]);
        $shippingAddress->method('getName')->willReturn('Marina Garcia');
        $shippingAddress->method('getStreet')->willReturn($street);
        $shippingAddress->method('getRegionId')->willReturn('155');
        $shippingAddress->setData('country_id', 'ES');
        $shippingAddress->setData('shipping_incl_tax', 0.0);
        $shippingAddress->setData('shipping_method', '');

        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('Tienda');
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/media/');
        $store->method('getUrl')->willReturn('https://shop.example.com/sequra/expresscheckout/cartupdate');

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getStore', 'getAllVisibleItems'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getStore')->willReturn($store);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $quote->setData('grand_total', 43.0);
        $quote->setData('quote_currency_code', 'EUR');

        $data = $this->decorator()->buildCartData(self::FORM, $quote);

        /** @var array<string, string> $address */
        $address = $data['address'];

        return $address;
    }

    /**
     * Builds the decorator with every collaborator stubbed to the quiet answer.
     *
     * @return CartSummaryFormDecorator
     */
    private function decorator(): CartSummaryFormDecorator
    {
        $emailResolver = $this->createMock(QuoteEmailResolver::class);
        $emailResolver->method('resolve')->willReturn('marina@example.com');

        $directoryHelper = $this->createMock(DirectoryHelper::class);
        $directoryHelper->method('isRegionRequired')->willReturn(false);

        $logoPathResolver = $this->createMock(LogoPathResolver::class);
        $logoPathResolver->method('getPath')->willReturn('');

        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('formkey');

        return new CartSummaryFormDecorator(
            $this->createMock(ImageHelper::class),
            $this->createMock(ItemResolverInterface::class),
            $this->createMock(ShippingMethodConverter::class),
            $logoPathResolver,
            $formKey,
            $emailResolver,
            $directoryHelper
        );
    }
}
