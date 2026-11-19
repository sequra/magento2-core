<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class AddresslessSolicitTest
 *
 * What a quote solicited without an address is allowed to claim about delivery: nothing.
 *
 * A shopper with no address gets a quote carrying only a country, and no rates are collected for
 * it — there is no destination to quote against. The order therefore has to go to SeQura with no
 * delivery method at all, which is what this branch's contract says it does.
 *
 * The cart clone makes that easy to break: it copies the live cart's chosen shipping method so the
 * customer path can honour it, and that method survives into a quote that has no rates behind it.
 * Left in place it reads as a settled delivery costing nothing — the create-order request sends it,
 * and the checkout form shows free shipping where it should show that delivery is not yet known.
 */
class AddresslessSolicitTest extends TestCase
{
    /**
     * A method copied off the cart does not survive a solicit that collected no rates.
     *
     * @return void
     */
    public function testDropsAShippingMethodNoRateEverBacked(): void
    {
        $shippingAddress = $this->address('ES', 'flatrate_flatrate');

        $this->assertTrue($this->resolve($shippingAddress));
        $this->assertSame('', (string)$shippingAddress->getData('shipping_method'));
    }

    /**
     * The country is still what the solicit needs, and still survives.
     *
     * @return void
     */
    public function testKeepsTheCountryTheMerchantIsPickedWith(): void
    {
        $shippingAddress = $this->address('ES', 'flatrate_flatrate');

        $this->resolve($shippingAddress);

        $this->assertSame('ES', (string)$shippingAddress->getData('country_id'));
    }

    /**
     * Runs resolve() over a guest quote carrying the given shipping address.
     *
     * @param Address $shippingAddress
     *
     * @return bool What resolve() answered.
     */
    private function resolve(Address $shippingAddress): bool
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'collectTotals', 'getPayment'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($this->address('', ''));
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getPayment')->willReturn($this->payment());
        // A guest: no account to import a default address from, so resolve() prepares the quote
        // without one.
        $quote->setData('customer_id', 0);

        $resolver = new QuoteShippingResolver(
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(ResolverInterface::class),
            $this->createMock(RegionFactory::class)
        );

        return $resolver->resolve($quote);
    }

    /**
     * A quote address carrying the given country and shipping method.
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

    /**
     * A payment the resolver can select SeQura on.
     *
     * @return Quote\Payment&\PHPUnit\Framework\MockObject\MockObject
     */
    private function payment()
    {
        $payment = $this->createMock(Quote\Payment::class);
        $payment->method('setMethod')->willReturnSelf();

        return $payment;
    }
}
