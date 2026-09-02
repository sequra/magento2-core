<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;
use Throwable;

/**
 * Class SolicitRateLimiter
 *
 * Lightweight fixed-window rate limiter for the Express Checkout solicit endpoints. Each solicit
 * creates a SeQura order (and, on the product page, a temporary quote), so each caller is throttled
 * to a sane number of attempts per window to bound accidental or abusive flooding.
 * Backed by the shared cache so the counter is consistent across web nodes.
 *
 * Two buckets, both binding — an attempt is refused when *either* is spent:
 *
 * - The session bucket keeps one shopper's own budget tight (self::SESSION_LIMIT). It is not
 *   sufficient on its own: these endpoints require no login, so a caller that sends no session
 *   cookie is handed a brand new session — and therefore a brand new bucket — on every request.
 * - The address bucket is the one such a caller cannot escape, because it is keyed on the
 *   connection's own peer address rather than on anything the caller states about itself.
 *
 * Each key is namespaced by its own cache prefix and hashed, so a session id can never land on an
 * address counter or the other way round.
 *
 * Why the peer address, and why the address budget is so much larger than the session one:
 *
 * The address is read through Magento's {@see RemoteAddress}, but through an instance wired for
 * this class (the SequraExpressSolicitPeerAddress virtual type in the module's etc/di.xml) that
 * looks at REMOTE_ADDR and nothing else. That is deliberate. A stock Magento lists
 * X-Forwarded-For as an alternative header and leaves its trusted-proxy list unset, so the
 * address it reports is the *first*, entirely client-supplied entry of that chain — free to
 * change per request, and therefore worthless as a throttle key. REMOTE_ADDR is the connection's
 * own peer and the caller cannot forge it.
 *
 * The cost is granularity, and it must be stated plainly: the real shopper address is NOT
 * available to us. This store is fronted by a Cloudflare tunnel and nothing in the deployment
 * configures trusted proxies or remote_addr handling, so REMOTE_ADDR is the proxy's and every
 * shopper arriving through the same front collapses into a single address bucket. Of the two ways
 * to be wrong, a bucket that is too generous beats one that locks real shoppers out of checkout,
 * so self::ADDRESS_LIMIT is sized as a flood ceiling for a whole storefront's worth of traffic
 * rather than as a per-shopper quota. It still turns "unlimited SeQura orders from one cookie-less
 * caller" into a bounded number per minute, which is the hole it exists to close. Tightening it,
 * or keying on the forwarded header instead, only becomes safe once a deployment sets trusted
 * proxies so that header can be believed.
 *
 * The cap is best-effort, not hard: load → +1 → save is a non-atomic read-modify-write, so
 * concurrent solicits from the same caller can interleave and let the count slip slightly past the
 * limit. That is intentional — the goal is to bound flooding, not enforce a strict quota — and
 * {@see isExceeded} fails open so a cache/serialization hiccup never blocks a real checkout.
 * A hard guarantee would need an atomic increment (cache-backend-specific) or a lock, which is not
 * warranted for this throttle.
 */
class SolicitRateLimiter
{
    /**
     * Maximum number of solicit attempts allowed per session within self::WINDOW_SECONDS.
     */
    private const SESSION_LIMIT = 30;

    /**
     * Maximum number of solicit attempts allowed per client address within self::WINDOW_SECONDS.
     *
     * Deliberately generous: the address may be a shared proxy's, so this bucket can be the whole
     * storefront's. See the class docblock.
     */
    private const ADDRESS_LIMIT = 600;

    /**
     * Length of the fixed rate-limit window, in seconds.
     */
    private const WINDOW_SECONDS = 60;

    /**
     * Cache key prefix for per-session solicit counters.
     */
    private const SESSION_CACHE_PREFIX = 'sequra_express_solicit_sid_';

    /**
     * Cache key prefix for per-client-address solicit counters.
     */
    private const ADDRESS_CACHE_PREFIX = 'sequra_express_solicit_ip_';

    /**
     * Stand-in key for a request whose peer address cannot be read at all, so those attempts still
     * land in a bucket instead of in none. Shared, hence counted against self::ADDRESS_LIMIT.
     */
    private const UNKNOWN_ADDRESS = 'unknown';

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;
    /**
     * @var RemoteAddress
     */
    private RemoteAddress $remoteAddress;

    /**
     * SolicitRateLimiter constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        RemoteAddress $remoteAddress
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Registers an attempt for the given session and reports whether the caller is over a limit.
     *
     * Counts against the session bucket and the client-address bucket, and refuses when either is
     * spent. Both are always registered, so tripping one never hides the attempt from the other.
     *
     * @param string $sessionId Session id of the caller. Every Express Checkout endpoint passes it
     *                          and it is never empty — reading it starts the session. On its own it
     *                          is not a throttle, because a cookie-less caller gets a fresh one per
     *                          request; the address bucket is what binds them.
     *
     * @return bool True when this attempt is over an allowed limit and should be rejected.
     */
    public function isExceeded(string $sessionId): bool
    {
        $sessionSpent = $this->register(
            self::SESSION_CACHE_PREFIX . $this->digest($sessionId),
            self::SESSION_LIMIT
        );
        $addressSpent = $this->register(
            self::ADDRESS_CACHE_PREFIX . $this->digest($this->clientAddress()),
            self::ADDRESS_LIMIT
        );

        return $sessionSpent || $addressSpent;
    }

    /**
     * Reads the peer address of the current connection, or the shared stand-in when there is none.
     *
     * {@see RemoteAddress::getRemoteAddress} answers false when it finds no usable address.
     *
     * @return string
     */
    private function clientAddress(): string
    {
        $address = $this->remoteAddress->getRemoteAddress();

        return is_string($address) && $address !== '' ? $address : self::UNKNOWN_ADDRESS;
    }

    /**
     * Hashes a key part into a cache-id-safe token.
     *
     * The raw parts are not cache-id safe on their own — an IPv6 address carries colons, which the
     * cache frontend rejects, and a rejected id would silently fail the whole check open. Hashing
     * also keeps shopper addresses and session ids out of the cache keys themselves.
     *
     * @param string $value Raw key part.
     *
     * @return string
     */
    private function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Registers one attempt against a single bucket and reports whether that bucket is now spent.
     *
     * Fail-open: any cache/serialization error is swallowed so a transient cache problem never
     * blocks a legitimate checkout.
     *
     * @param string $cacheKey Namespaced cache key of the bucket.
     * @param int $limit Attempts this bucket allows per window.
     *
     * @return bool True when this attempt is over the bucket's limit.
     */
    private function register(string $cacheKey, int $limit): bool
    {
        try {
            $now = time();
            $raw = $this->cache->load($cacheKey);
            $state = $raw ? $this->serializer->unserialize($raw) : null;

            if (!is_array($state)
                || !isset($state['start'], $state['count'])
                || ($now - (int)$state['start']) >= self::WINDOW_SECONDS
            ) {
                $state = ['start' => $now, 'count' => 0];
            }

            $state['count'] = (int)$state['count'] + 1;
            $this->cache->save((string)$this->serializer->serialize($state), $cacheKey, [], self::WINDOW_SECONDS);

            return $state['count'] > $limit;
        } catch (Throwable $e) {
            return false;
        }
    }
}
