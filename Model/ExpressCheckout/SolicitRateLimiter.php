<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class SolicitRateLimiter
 *
 * Lightweight fixed-window rate limiter for the Express Checkout solicit endpoints. Each solicit
 * creates a SeQura order (and, on the product page, a temporary quote), so an authenticated caller
 * is throttled to a sane number of attempts per window to bound accidental or abusive flooding.
 * Backed by the shared cache so the counter is consistent across web nodes.
 *
 * The cap is best-effort, not hard: load → +1 → save is a non-atomic read-modify-write, so
 * concurrent solicits from the same caller can interleave and let the count slip slightly past
 * self::LIMIT. That is intentional — the goal is to bound flooding, not enforce a strict quota —
 * and {@see isExceeded} fails open so a cache/serialization hiccup never blocks a real checkout.
 * A hard guarantee would need an atomic increment (cache-backend-specific) or a lock, which is not
 * warranted for this throttle.
 */
class SolicitRateLimiter
{
    /**
     * Maximum number of solicit attempts allowed per key within self::WINDOW_SECONDS.
     */
    private const LIMIT = 30;

    /**
     * Length of the fixed rate-limit window, in seconds.
     */
    private const WINDOW_SECONDS = 60;

    /**
     * Cache key prefix for per-caller solicit counters.
     */
    private const CACHE_PREFIX = 'sequra_express_solicit_';

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * SolicitRateLimiter constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(CacheInterface $cache, SerializerInterface $serializer)
    {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * Registers an attempt for the given key and reports whether the caller has exceeded the limit.
     *
     * Fail-open: any cache/serialization error is swallowed so a transient cache problem never
     * blocks a legitimate checkout.
     *
     * @param string $key Stable per-caller identifier (e.g. the customer ID).
     *
     * @return bool True when this attempt is over the allowed limit and should be rejected.
     */
    public function isExceeded(string $key): bool
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $key;
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

            return $state['count'] > self::LIMIT;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
