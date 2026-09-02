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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\CartSummaryFormDecorator;
use Sequra\Core\Model\QuoteEmailResolver;

/**
 * Class CartSummaryAmountsTest
 *
 * The two figures on the CartSummary page that move with every shopper change: the total and the
 * shipping cost.
 *
 * They have to travel on the cartDataReady payload rather than on the solicited order alone,
 * because the payload is the only thing an update answers with — the form never re-reads the
 * order's lines or its metadata after a re-solicit, so a total or a shipping line left out of
 * here is a summary frozen at whatever the page booted with.
 *
 * What they must be is settled elsewhere: both are the exact expressions
 * {@see \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder} sends SeQura for the same
 * quote, so the shopper confirms the figure SeQura was asked to fund. These tests pin that
 * agreement, and pin the one case where no shipping cost is sent at all.
 */
class CartSummaryAmountsTest extends TestCase
{
    /**
     * A form snippet shaped like the one a solicit returns.
     */
    private const FORM = '<div data-base-url="https://sandbox.sequracdn.com/orders/abc123/form"></div>';

    /**
     * @var MockObject&Address
     */
    private $shippingAddress;

    /**
     * @var MockObject&Quote
     */
    private $quote;

    /**
     * The grand total is sent in cents with tax, off the same getter the create-order request
     * declares as `order_total_with_tax`.
     *
     * @return void
     */
    public function testSendsTheQuoteGrandTotalInCents(): void
    {
        $data = $this->decorator()->buildCartData(self::FORM, $this->quoteWith(43.0, 5.0, 'flatrate_flatrate'));

        $this->assertSame(4300, $data['totalWithTax']);
    }

    /**
     * The shipping cost is the address's own taxed shipping amount — the figure the grand total
     * above actually contains — not the carrier rate the method list quotes.
     *
     * @return void
     */
    public function testSendsTheAppliedShippingCostInCents(): void
    {
        $data = $this->decorator()->buildCartData(self::FORM, $this->quoteWith(43.0, 5.0, 'flatrate_flatrate'));

        $this->assertSame(500, $data['shippingCostWithTax']);
    }

    /**
     * Free shipping is 0, and 0 is sent: the form renders it as "Gratis", which is true.
     *
     * @return void
     */
    public function testSendsFreeShippingAsZero(): void
    {
        $data = $this->decorator()->buildCartData(self::FORM, $this->quoteWith(38.0, 0.0, 'freeshipping_freeshipping'));

        $this->assertSame(0, $data['shippingCostWithTax']);
    }

    /**
     * A quote with no rate applied has no shipping cost, and the key is left out rather than sent
     * as 0: the form renders a missing cost as a dash and 0 as free shipping, and promising free
     * shipping before a carrier exists is the wrong half of that choice.
     *
     * @return void
     */
    public function testOmitsTheShippingCostWhenNoRateIsApplied(): void
    {
        $data = $this->decorator()->buildCartData(self::FORM, $this->quoteWith(38.0, 0.0, ''));

        $this->assertArrayNotHasKey('shippingCostWithTax', $data);
        $this->assertSame(3800, $data['totalWithTax']);
    }

    /**
     * Rounding is to the nearest cent, the same way the create-order request rounds, so the two
     * cannot land a cent apart on a taxed total.
     *
     * @return void
     */
    public function testRoundsToTheNearestCent(): void
    {
        $data = $this->decorator()->buildCartData(self::FORM, $this->quoteWith(43.005, 4.995, 'flatrate_flatrate'));

        $this->assertSame(4301, $data['totalWithTax']);
        $this->assertSame(500, $data['shippingCostWithTax']);
    }

    /**
     * Builds a quote carrying the given money, with everything else the payload reads stubbed
     * away: no items, no rates, no regions.
     *
     * The money lives in the model's own data, not in stubbed methods — `getGrandTotal()` and
     * `getShippingInclTax()` are Magento magic getters over it, so setting the data is what
     * exercises the real getters the decorator calls.
     *
     * @param float $grandTotal Quote grand total, in the quote currency.
     * @param float $shippingInclTax Taxed shipping amount on the shipping address.
     * @param string $shippingMethod Applied rate code, or '' for a quote with none.
     *
     * @return Quote
     */
    private function quoteWith(float $grandTotal, float $shippingInclTax, string $shippingMethod): Quote
    {
        $this->shippingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllShippingRates', 'getName', 'getStreet', 'getRegionId'])
            ->getMock();
        $this->shippingAddress->method('getAllShippingRates')->willReturn([]);
        $this->shippingAddress->method('getName')->willReturn('Marina Garcia');
        $this->shippingAddress->method('getStreet')->willReturn(['Carrer de Pallars 128']);
        $this->shippingAddress->method('getRegionId')->willReturn('155');
        $this->shippingAddress->setData('shipping_incl_tax', $shippingInclTax);
        $this->shippingAddress->setData('shipping_method', $shippingMethod);
        $this->shippingAddress->setData('country_id', 'ES');

        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('Tienda');
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/media/');
        $store->method('getUrl')->willReturn('https://shop.example.com/sequra/expresscheckout/cartupdate');

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getStore', 'getAllVisibleItems'])
            ->getMock();
        $this->quote->method('getShippingAddress')->willReturn($this->shippingAddress);
        $this->quote->method('getStore')->willReturn($store);
        $this->quote->method('getAllVisibleItems')->willReturn([]);
        $this->quote->setData('grand_total', $grandTotal);
        $this->quote->setData('quote_currency_code', 'EUR');

        return $this->quote;
    }

    /**
     * Builds the decorator with every collaborator stubbed to the quiet answer, so only the money
     * the quote carries reaches the payload.
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
