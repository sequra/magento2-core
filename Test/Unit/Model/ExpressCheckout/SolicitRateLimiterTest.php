<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\ExpressCheckout\SolicitRateLimiter;

/**
 * Class SolicitRateLimiterTest
 *
 * What the Express Checkout solicit throttle keys on, now that these endpoints require no login.
 *
 * The session alone is not a key: a caller that sends no session cookie is handed a brand new
 * session on every request, so a session-only counter hands it a brand new budget every time —
 * and each of these endpoints solicits a real SeQura order. The limiter therefore also counts the
 * connection's peer address, which the caller cannot renew, and refuses when either bucket is
 * spent. These tests pin both halves: the cookie-less caller is bounded, and two ordinary shoppers
 * still never spend from each other's budget.
 */
class SolicitRateLimiterTest extends TestCase
{
    /**
     * Attempts allowed per session per window — {@see SolicitRateLimiter::SESSION_LIMIT}.
     */
    private const SESSION_LIMIT = 30;

    /**
     * Attempts allowed per client address per window — {@see SolicitRateLimiter::ADDRESS_LIMIT}.
     * Far larger than the session budget on purpose: the address may be a shared proxy's.
     */
    private const ADDRESS_LIMIT = 600;

    /**
     * Stands in for the shared cache, so every limiter built here counts into the same store.
     *
     * @var array<string, string>
     */
    private $cacheStore = [];

    /**
     * A caller that sends no session cookie gets a fresh session — and so a fresh session bucket —
     * on every request. The address bucket is what still bounds it.
     *
     * @return void
     */
    public function testACallerWithoutASessionIsStillThrottled(): void
    {
        $limiter = $this->limiter('198.51.100.7');

        for ($attempt = 1; $attempt <= self::ADDRESS_LIMIT; $attempt++) {
            $this->assertFalse(
                $limiter->isExceeded($this->freshSessionId()),
                sprintf('Attempt %d from a brand new session should still be inside the address budget.', $attempt)
            );
        }

        $this->assertTrue(
            $limiter->isExceeded($this->freshSessionId()),
            'Once the address budget is spent, a brand new session must not buy another one.'
        );
    }

    /**
     * Two shoppers, each with their own session and their own address, keep separate budgets: one
     * spending its own to the last attempt leaves the other untouched.
     *
     * @return void
     */
    public function testTwoShoppersDoNotShareABucket(): void
    {
        $marina = $this->limiter('203.0.113.10');
        $pau = $this->limiter('203.0.113.20');

        for ($attempt = 1; $attempt <= self::SESSION_LIMIT; $attempt++) {
            $this->assertFalse($marina->isExceeded('session-marina'), 'Marina is inside her own budget.');
        }

        $this->assertTrue($marina->isExceeded('session-marina'), 'Marina has spent her session budget.');

        $this->assertFalse(
            $pau->isExceeded('session-pau'),
            'Pau must not be refused because another shopper spent their own budget.'
        );
    }

    /**
     * The two buckets are namespaced, so a session id can never be counted as an address or the
     * other way round — even when they are the very same string.
     *
     * @return void
     */
    public function testSessionAndAddressBucketsNeverCollide(): void
    {
        $limiter = $this->limiter('192.0.2.44');

        for ($attempt = 1; $attempt <= self::SESSION_LIMIT; $attempt++) {
            $limiter->isExceeded('192.0.2.44');
        }

        $this->assertTrue(
            $limiter->isExceeded('192.0.2.44'),
            'The session named exactly like the address has spent its own, smaller budget.'
        );
        $this->assertFalse(
            $limiter->isExceeded('another-session'),
            'The address budget must not have been drained by the identically named session.'
        );
    }

    /**
     * A request whose peer address cannot be read is still counted, against a shared bucket, rather
     * than escaping the throttle altogether.
     *
     * @return void
     */
    public function testARequestWithNoReadableAddressIsStillThrottled(): void
    {
        $limiter = $this->limiter(false);

        for ($attempt = 1; $attempt <= self::ADDRESS_LIMIT; $attempt++) {
            $this->assertFalse($limiter->isExceeded($this->freshSessionId()), 'Inside the shared budget.');
        }

        $this->assertTrue(
            $limiter->isExceeded($this->freshSessionId()),
            'An unreadable address must not mean an unlimited one.'
        );
    }

    /**
     * Builds a limiter that sees the given peer address, over the cache store shared by the test.
     *
     * @param string|false $clientAddress What RemoteAddress reports; false when it finds none.
     *
     * @return SolicitRateLimiter
     */
    private function limiter($clientAddress): SolicitRateLimiter
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(function (string $identifier) {
            return $this->cacheStore[$identifier] ?? false;
        });
        $cache->method('save')->willReturnCallback(
            function (string $data, string $identifier) {
                $this->cacheStore[$identifier] = $data;

                return true;
            }
        );

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(function ($value) {
            return (string)json_encode($value);
        });
        $serializer->method('unserialize')->willReturnCallback(function ($value) {
            return json_decode((string)$value, true);
        });

        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn($clientAddress);

        return new SolicitRateLimiter($cache, $serializer, $remoteAddress);
    }

    /**
     * A session id nobody has used before — what a request arriving with no session cookie gets.
     *
     * @return string
     */
    private function freshSessionId(): string
    {
        return uniqid('session-', true);
    }
}
