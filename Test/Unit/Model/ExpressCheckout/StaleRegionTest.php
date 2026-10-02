<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;

/**
 * Class StaleRegionTest
 *
 * The region on the quote is a fact about the address currently written on it, never a leftover
 * from the one before.
 *
 * The express address sheet has no country field, so a shopper moving within their own country
 * sends a change that replaces the street, postcode and city while naming no country at all. If
 * nothing can be derived for the new address — the sheet sent no regionId, or the country is one
 * the postcode map does not cover — the region already on the address describes somewhere the
 * shopper no longer lives. Keeping it is worse than having none: a flat-rate carrier quotes the
 * mismatched address without complaint, and the order goes out with the wrong province on the
 * label.
 *
 * So an underivable region clears whatever was there, whether or not the country moved with it.
 */
class StaleRegionTest extends TestCase
{
    /**
     * A move inside Italy, which the postcode derivation does not cover, with no region picked.
     *
     * @return void
     */
    public function testClearsTheOldRegionWhenNoneCanBeDerivedForTheNewAddress(): void
    {
        $address = $this->addressIn('IT', '00100', 5, 'Lazio', 'RM');

        $this->applyMove($address, 'Via Torino 12', '20121', 'Milano');

        $this->assertSame(0, (int)$address->getData('region_id'));
        $this->assertSame('', (string)$address->getData('region'));
        $this->assertSame('', (string)$address->getData('region_code'));
    }

    /**
     * The new postcode's own province is written when there is one, so clearing never costs a
     * region that could be derived.
     *
     * @return void
     */
    public function testWritesTheProvinceDerivedFromTheNewPostcode(): void
    {
        $address = $this->addressIn('ES', '46001', 30, 'Valencia', 'V');

        $this->applyMove($address, 'Carrer de Pallars 128', '08018', 'Barcelona', 'Barcelona', 12, 'B');

        $this->assertSame(12, (int)$address->getData('region_id'));
        $this->assertSame('Barcelona', (string)$address->getData('region'));
        $this->assertSame('B', (string)$address->getData('region_code'));
    }

    /**
     * Applies an address change carrying no country and no regionId — exactly what the express
     * sheet sends for a move inside the shopper's own country.
     *
     * @param Address $shippingAddress Address the change is written onto.
     * @param string $street
     * @param string $postcode
     * @param string $city
     * @param string $derivable Province the postcode maps to, or '' when it maps to none.
     * @param int $derivedId Region id the directory returns for that province.
     * @param string $derivedCode Region code the directory returns for that province.
     *
     * @return void
     */
    private function applyMove(
        Address $shippingAddress,
        string $street,
        string $postcode,
        string $city,
        string $derivable = '',
        int $derivedId = 0,
        string $derivedCode = ''
    ): void {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'collectTotals'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($this->selfSufficientBillingAddress());
        $quote->method('collectTotals')->willReturnSelf();

        $resolver = new QuoteShippingResolver(
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(ResolverInterface::class),
            $this->regionFactory($derivable, $derivedId, $derivedCode)
        );

        // No carrier can be quoted for the new address, so this answers false — after the address
        // itself has already been written, which is what these cases are about.
        $resolver->applyChange($quote, [
            'address' => [
                'givenName' => 'Marina',
                'surnames' => 'Garcia',
                'addressLine1' => $street,
                'postalCode' => $postcode,
                'city' => $city,
                'mobilePhone' => '600123456',
            ],
        ]);
    }

    /**
     * A shipping address already carrying a full region, offering no rates for anything new.
     *
     * @param string $country
     * @param string $postcode
     * @param int $regionId
     * @param string $region
     * @param string $regionCode
     *
     * @return Address
     */
    private function addressIn(
        string $country,
        string $postcode,
        int $regionId,
        string $region,
        string $regionCode
    ): Address {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllShippingRates', 'validate'])
            ->getMock();
        $address->method('getAllShippingRates')->willReturn([]);
        // Placeable unless a case says otherwise; what Magento demands of an address is its own
        // question, and {@see PlaceableAddressTest} is where it is asked.
        $address->method('validate')->willReturn(true);
        $address->setData('country_id', $country);
        $address->setData('postcode', $postcode);
        $address->setData('region_id', $regionId);
        $address->setData('region', $region);
        $address->setData('region_code', $regionCode);

        return $address;
    }

    /**
     * A billing address Magento would accept on its own, so the shipping address is not copied
     * over it and these cases stay about the region alone.
     *
     * @return Address
     */
    private function selfSufficientBillingAddress(): Address
    {
        $billing = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate'])
            ->getMock();
        $billing->method('validate')->willReturn(true);

        return $billing;
    }

    /**
     * A directory that knows the given province and nothing else.
     *
     * @param string $province Province name the directory resolves, or '' for a directory that is
     *                         never consulted.
     * @param int $id
     * @param string $code
     *
     * @return RegionFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private function regionFactory(string $province, int $id, string $code)
    {
        $region = $this->createMock(Region::class);
        $region->method('loadByName')->willReturnSelf();
        $region->method('getId')->willReturn($province === '' ? null : $id);
        $region->method('getCode')->willReturn($code);

        $factory = $this->createMock(RegionFactory::class);
        $factory->method('create')->willReturn($region);

        return $factory;
    }
}
