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
 * Class ResolvableShippingCountryTest
 *
 * What the storefront availability probe is allowed to call the shopper's delivery country.
 *
 * Only the shopper's own data counts: their default shipping address, or the country already on
 * the cart. When neither names one the probe answers '' — deliberately short of the store view's
 * locale, which {@see QuoteShippingResolver::resolveCountry} still falls back to when it has to
 * seed a quote for a solicit.
 *
 * The distinction is what keeps a guess from becoming a verdict.
 * {@see \Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator} renders the inline
 * "not available" message on a negative per-country answer and renders nothing when no country is
 * given, so reporting the locale region here would tell every guest on a store view SeQura does
 * not cover that SeQura is unavailable to them — on the strength of the storefront's language
 * setting rather than anything about where their goods are going.
 */
class ResolvableShippingCountryTest extends TestCase
{
    /**
     * The cart's own country is the shopper's, so it is reported.
     *
     * @return void
     */
    public function testReportsTheCountryTheCartCarries(): void
    {
        $this->assertSame('ES', $this->resolver('en_US')->getResolvableShippingCountry($this->guestQuote('ES')));
    }

    /**
     * With nothing naming a country, the probe says so instead of answering with the store view's
     * locale region.
     *
     * @return void
     */
    public function testReportsNoCountryRatherThanTheLocaleRegion(): void
    {
        $this->assertSame(
            '',
            $this->resolver('en_US')->getResolvableShippingCountry($this->guestQuote('')),
            'US came from the store view locale, not from the shopper.'
        );
    }

    /**
     * Holds for a locale SeQura does serve, too: the point is the provenance of the country, not
     * whether the answer would have been convenient.
     *
     * @return void
     */
    public function testReportsNoCountryEvenWhenTheLocaleWouldBeSupported(): void
    {
        $this->assertSame('', $this->resolver('es_ES')->getResolvableShippingCountry($this->guestQuote('')));
    }

    /**
     * Builds the resolver over a store view locale, with no customer to look up.
     *
     * @param string $locale Store view locale, e.g. 'en_US'.
     *
     * @return QuoteShippingResolver
     */
    private function resolver(string $locale): QuoteShippingResolver
    {
        $localeResolver = $this->createMock(ResolverInterface::class);
        $localeResolver->method('getLocale')->willReturn($locale);

        return new QuoteShippingResolver(
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(CartRepositoryInterface::class),
            $localeResolver,
            $this->createMock(RegionFactory::class)
        );
    }

    /**
     * A guest cart whose shipping address carries the given country, or none at all.
     *
     * @param string $country ISO2 country on the cart's shipping address, or '' for none.
     *
     * @return Quote
     */
    private function guestQuote(string $country): Quote
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $address->setData('country_id', $country);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->setData('customer_id', 0);

        return $quote;
    }
}
