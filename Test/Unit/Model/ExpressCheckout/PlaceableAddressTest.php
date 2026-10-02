<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Rate;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class PlaceableAddressTest
 *
 * An address the shopper gives on the express sheet is refused here if Magento would refuse it at
 * placeOrder.
 *
 * The endpoint can only police a fixed list of fields. What Magento actually demands depends on
 * where the parcel is going — `general/region/state_required`, the country's postcode rules — and a
 * flat-rate carrier happily quotes an address that misses those. Letting such a change through
 * means SeQura approves and charges for an order Magento then refuses to create: money taken with
 * nothing behind it.
 *
 * So the same `validate()` that placeOrder runs decides here, while the shopper is still on the
 * screen and can fix it.
 */
class PlaceableAddressTest extends TestCase
{
    /**
     * A complete address that Magento would place goes through.
     *
     * @return void
     */
    public function testAcceptsAnAddressMagentoWouldPlace(): void
    {
        $this->assertTrue($this->applyAddressChange(true, true));
    }

    /**
     * A shipping address Magento would reject — a region-required country with no region on it,
     * say — is refused rather than solicited.
     *
     * @return void
     */
    public function testRefusesAShippingAddressMagentoWouldReject(): void
    {
        $this->assertFalse($this->applyAddressChange(['Region is required.'], true));
    }

    /**
     * The billing address counts too: the express sheet collects one address, and a billing copy
     * that does not stand on its own fails placeOrder just as surely.
     *
     * @return void
     */
    public function testRefusesABillingAddressMagentoWouldReject(): void
    {
        $this->assertFalse($this->applyAddressChange(true, ['Telephone is required.']));
    }

    /**
     * An email saved before any address is still accepted: that quote is deliberately addressless
     * and is not expected to validate yet.
     *
     * @return void
     */
    public function testStillAcceptsAnEmailSavedBeforeAnyAddress(): void
    {
        $resolver = $this->resolver();
        $quote = $this->quote(
            $this->address(['Street is required.']),
            $this->address(['Street is required.'])
        );

        $this->assertTrue($resolver->applyChange($quote, ['email' => 'marina@example.com']));
    }

    /**
     * Applies an address change to a quote whose addresses validate as given.
     *
     * @param bool|string[] $shippingVerdict What validate() answers on the shipping address.
     * @param bool|string[] $billingVerdict What validate() answers on the billing address.
     *
     * @return bool What applyChange answered.
     */
    private function applyAddressChange($shippingVerdict, $billingVerdict): bool
    {
        $quote = $this->quote($this->address($shippingVerdict), $this->address($billingVerdict));

        return $this->resolver()->applyChange($quote, [
            'address' => [
                'givenName' => 'Marina',
                'surnames' => 'Garcia',
                'addressLine1' => 'Carrer de Pallars 128',
                'postalCode' => '08018',
                'city' => 'Barcelona',
                'mobilePhone' => '600123456',
                'regionId' => '',
            ],
        ]);
    }

    /**
     * A quote offering one usable rate, so only the address verdict decides the answer.
     *
     * @param Address $shippingAddress
     * @param Address $billingAddress
     *
     * @return Quote
     */
    private function quote(Address $shippingAddress, Address $billingAddress): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'collectTotals', 'getPayment'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getPayment')->willReturn($this->payment());

        return $quote;
    }

    /**
     * An address answering the given verdict, carrying one flat rate.
     *
     * @param bool|string[] $verdict What validate() answers.
     *
     * @return Address
     */
    private function address($verdict): Address
    {
        $rate = $this->createMock(Rate::class);
        $rate->method('getCode')->willReturn('flatrate_flatrate');
        $rate->method('getPrice')->willReturn(4.95);
        $rate->method('getErrorMessage')->willReturn(false);

        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAllShippingRates',
                'validate',
                'exportCustomerAddress',
                'importCustomerAddressData',
            ])
            ->getMock();
        $address->method('getAllShippingRates')->willReturn([$rate]);
        $address->method('validate')->willReturn($verdict);
        // Reached only when the billing address does not stand on its own and the shipping one is
        // copied over it; the copy itself is not what these cases are about.
        $address->method('importCustomerAddressData')->willReturnSelf();
        $address->setData('country_id', 'ES');

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

    /**
     * A resolver whose directory never resolves a region, so only validate() decides the answer.
     *
     * @return QuoteShippingResolver
     */
    private function resolver(): QuoteShippingResolver
    {
        $region = $this->createMock(Region::class);
        $region->method('loadByName')->willReturnSelf();
        $region->method('getId')->willReturn(null);

        $regionFactory = $this->createMock(RegionFactory::class);
        $regionFactory->method('create')->willReturn($region);

        return new QuoteShippingResolver(
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(ResolverInterface::class),
            $regionFactory
        );
    }
}
