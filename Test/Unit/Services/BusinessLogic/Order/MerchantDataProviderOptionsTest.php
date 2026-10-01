<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic\Order;

use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\ExpressCheckoutFlow;
use Sequra\Core\Services\BusinessLogic\Order\MerchantDataProvider;

/**
 * Class MerchantDataProviderOptionsTest
 *
 * Which flows may tell SeQura that a create-order request's addresses might be missing.
 *
 * Express Checkout has to: it solicits before the shopper has typed an address, because
 * collecting one is what the express screen is for. Regular checkout must not, and the difference
 * is not cosmetic — `addresses_may_be_missing` is what stops SeQura refusing an order whose
 * address block arrived empty or broken, and that refusal is how a misbehaving third-party address
 * plugin or a mis-mapped B2B address surfaces at checkout instead of becoming an order nobody can
 * ship. Declaring the flag on every request hands that guard back for a flag only one flow needs.
 *
 * getOptions() is a context-free hook — no cart, no order, no flow — so {@see ExpressCheckoutFlow}
 * is what tells the two apart.
 */
class MerchantDataProviderOptionsTest extends TestCase
{
    /**
     * Outside an express solicit nothing is declared, which is what regular checkout sends.
     *
     * @return void
     */
    public function testDeclaresNoOptionsForRegularCheckout(): void
    {
        $this->assertNull($this->provider(new ExpressCheckoutFlow())->getOptions());
    }

    /**
     * Inside an express solicit the flag is declared.
     *
     * @return void
     */
    public function testDeclaresAddressesMayBeMissingForAnExpressSolicit(): void
    {
        $flow = new ExpressCheckoutFlow();
        $flow->enterSolicit();

        $options = $this->provider($flow)->getOptions();

        $this->assertNotNull($options);
        $this->assertTrue($options->getAddressesMayBeMissing());
    }

    /**
     * The marker is scoped to the solicit, not to the request: once the express call has left, a
     * later create-order request in the same request cycle is back to declaring nothing.
     *
     * @return void
     */
    public function testStopsDeclaringItOnceTheSolicitHasFinished(): void
    {
        $flow = new ExpressCheckoutFlow();
        $flow->enterSolicit();
        $flow->leaveSolicit();

        $this->assertNull($this->provider($flow)->getOptions());
    }

    /**
     * Builds the provider over the given flow marker.
     *
     * @param ExpressCheckoutFlow $flow
     *
     * @return MerchantDataProvider
     */
    private function provider(ExpressCheckoutFlow $flow): MerchantDataProvider
    {
        return new MerchantDataProvider($this->createMock(UrlInterface::class), $flow);
    }
}
