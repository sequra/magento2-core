<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\SolicitedQuoteRegistry;

/**
 * Class SolicitedQuoteRegistryTest
 *
 * That two Express Checkout forms open at once keep their own quotes.
 *
 * A single "last solicited quote" slot could not: the second solicit overwrote it, and the first
 * form's updates then landed on the second form's quote — mutated, re-solicited, and answered with
 * a cart the shopper was not looking at. A shopper opening a product form and then the cart form,
 * or simply two product tabs, hits this.
 *
 * So every solicit mints its own token, and the token is the only thing that names a quote.
 */
class SolicitedQuoteRegistryTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private $session = [];

    /**
     * The older form keeps its own quote after a newer solicit, which is the whole point.
     *
     * @return void
     */
    public function testEachFormKeepsItsOwnQuote(): void
    {
        $registry = $this->registry(['tokenOne', 'tokenTwo']);

        $product = $registry->remember(11);
        $cart = $registry->remember(22);

        $this->assertSame(11, $registry->resolve($product));
        $this->assertSame(22, $registry->resolve($cart));
    }

    /**
     * Two tabs of the same surface share a reusable draft, so the newer solicit retires the older
     * tab's token instead of leaving two tokens pointing at one quote.
     *
     * This is the case a per-token map does not fix on its own: {@see
     * \Sequra\Core\Model\ExpressCheckout\TemporaryCartBuilder} keeps one draft per surface and
     * empties and refills it, so both solicits are handed the same quote id. The older tab is
     * stale the moment the newer one solicits — its draft has been wiped — so its token has to
     * stop naming anything rather than name the cart the newer tab is showing.
     *
     * @return void
     */
    public function testASecondSolicitOnOneQuoteRetiresTheFirstToken(): void
    {
        $registry = $this->registry(['tokenOne', 'tokenTwo']);

        $firstTab = $registry->remember(11);
        $secondTab = $registry->remember(11);

        $this->assertSame(0, $registry->resolve($firstTab));
        $this->assertSame(11, $registry->resolve($secondTab));
    }

    /**
     * A token nobody minted names nothing — the same answer a stale one gets, so the map cannot
     * be probed by trying tokens.
     *
     * @return void
     */
    public function testAnUnknownTokenNamesNothing(): void
    {
        $registry = $this->registry(['tokenOne']);
        $registry->remember(11);

        $this->assertSame(0, $registry->resolve('neverMinted'));
    }

    /**
     * Nothing is remembered before a solicit has run.
     *
     * @return void
     */
    public function testAnEmptySessionNamesNothing(): void
    {
        $this->assertSame(0, $this->registry([])->resolve('tokenOne'));
    }

    /**
     * The map is bounded: past the ceiling the oldest flow is dropped, so a long-lived session
     * cannot grow without limit. The newest stay.
     *
     * @return void
     */
    public function testTheOldestFlowIsDroppedPastTheCeiling(): void
    {
        $tokens = [];
        for ($i = 1; $i <= 9; $i++) {
            $tokens[] = 'token' . $i;
        }

        $registry = $this->registry($tokens);
        $minted = [];
        foreach ($tokens as $index => $token) {
            $minted[] = $registry->remember(100 + $index);
        }

        $this->assertSame(0, $registry->resolve($minted[0]), 'the ninth solicit drops the first');
        $this->assertSame(101, $registry->resolve($minted[1]));
        $this->assertSame(108, $registry->resolve($minted[8]));
    }

    /**
     * Builds a registry over an in-memory session, minting the given tokens in order.
     *
     * @param string[] $tokens Tokens the random source hands out, oldest first.
     *
     * @return SolicitedQuoteRegistry
     */
    private function registry(array $tokens): SolicitedQuoteRegistry
    {
        $this->session = [];

        $session = $this->createMock(CustomerSession::class);
        $session->method('getData')->willReturnCallback(
            function ($key) {
                return $this->session[$key] ?? null;
            }
        );
        $session->method('__call')->willReturnCallback(
            function ($method, $args) {
                // setData() is not declared on the session class; it reaches Storage through
                // SessionManager::__call, which is what the registry relies on at runtime.
                if ($method === 'setData') {
                    $this->session[$args[0]] = $args[1];
                }

                return null;
            }
        );

        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->willReturnOnConsecutiveCalls(...$tokens ?: ['unused']);

        return new SolicitedQuoteRegistry($session, $random);
    }
}
