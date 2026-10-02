<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\TemporaryCartBuilder;

/**
 * Class ClonedCartDestinationTest
 *
 * What the cart / mini-cart clone has to carry over from the cart the shopper was looking at.
 *
 * The Express Checkout button on those surfaces is granted by an availability probe that reads the
 * live cart's shipping country
 * ({@see \Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver::getResolvableShippingCountry}),
 * but the order is solicited against a detached clone, and it is the clone that
 * {@see \Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver::resolve} resolves a country
 * from. A clone that starts with no country sends a guest — who has no customer default address to
 * fall back on — all the way to the store view's locale, so a shopper who estimated shipping for
 * one country would be solicited against the merchant of another, or refused outright.
 *
 * Copying the country is what keeps the two answers the same.
 */
class ClonedCartDestinationTest extends TestCase
{
    /**
     * The country the shopper estimated with reaches the clone.
     *
     * @return void
     */
    public function testCarriesTheSourceCartCountryOntoTheClone(): void
    {
        $this->assertSame('FR', (string)$this->cloneFrom('FR')->getData('country_id'));
    }

    /**
     * A cart that names no country leaves the clone naming none either, so the solicit still
     * resolves one for itself instead of inheriting an invented value.
     *
     * @return void
     */
    public function testLeavesTheCloneWithoutACountryWhenTheCartHasNone(): void
    {
        $this->assertSame('', (string)$this->cloneFrom('')->getData('country_id'));
    }

    /**
     * The shopper's selected shipping method is still carried alongside the country.
     *
     * @return void
     */
    public function testKeepsCarryingTheSelectedShippingMethod(): void
    {
        $this->assertSame('flatrate_flatrate', (string)$this->cloneFrom('FR')->getData('shipping_method'));
    }

    /**
     * Runs buildFromQuote against a one-line source cart shipping to $country, and returns the
     * clone's shipping address as the builder left it.
     *
     * @param string $country ISO2 country on the source cart's shipping address, or '' for none.
     *
     * @return Address
     */
    private function cloneFrom(string $country): Address
    {
        $cloneAddress = $this->address('', '');
        $quoteFactory = $this->createMock(QuoteFactory::class);
        $quoteFactory->method('create')->willReturn($this->emptyQuote($cloneAddress));

        // No customer id and no remembered draft id: a guest building a fresh clone.
        $customerSession = $this->createMock(CustomerSession::class);
        $customerSession->method('getCustomerId')->willReturn(null);

        $builder = new TemporaryCartBuilder(
            $customerSession,
            $this->createMock(ProductRepositoryInterface::class),
            $quoteFactory,
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(StoreManagerInterface::class)
        );

        $builder->buildFromQuote($this->sourceQuote($country));

        return $cloneAddress;
    }

    /**
     * A cart with one shippable line, a selected carrier and the given delivery country.
     *
     * @param string $country
     *
     * @return Quote
     */
    private function sourceQuote(string $country): Quote
    {
        $item = $this->createMock(Item::class);
        $item->method('getProduct')->willReturn($this->createMock(Product::class));
        $item->method('getBuyRequest')->willReturn(new DataObject(['qty' => 1]));

        $source = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems', 'getShippingAddress'])
            ->getMock();
        $source->method('getAllVisibleItems')->willReturn([$item]);
        $source->method('getShippingAddress')->willReturn($this->address($country, 'flatrate_flatrate'));
        $source->setData('store_id', 1);
        $source->setData('coupon_code', '');

        return $source;
    }

    /**
     * The freshly created clone: accepts the items and totals pass, and reports itself shippable.
     *
     * @param Address $shippingAddress Address the builder writes the carried-over values onto.
     *
     * @return Quote
     */
    private function emptyQuote(Address $shippingAddress): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(
                ['getShippingAddress', 'addProduct', 'collectTotals', 'isVirtual', 'getAllVisibleItems', 'getId']
            )
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        // A non-string return is addProduct's success signal.
        $quote->method('addProduct')->willReturn(null);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $quote->method('getId')->willReturn(99);

        return $quote;
    }

    /**
     * A quote address carrying nothing but the given country and shipping method.
     *
     * @param string $country
     * @param string $shippingMethod
     *
     * @return Address
     */
    private function address(string $country, string $shippingMethod): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $address->setData('country_id', $country);
        $address->setData('shipping_method', $shippingMethod);

        return $address;
    }
}
