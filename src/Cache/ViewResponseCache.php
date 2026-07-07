<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Cache;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Stores serialized JSON payloads keyed by view cache signatures.
 *
 * Every stored entry gets an expiration — descriptors without an explicit TTL fall
 * back to the configured default (one day), so the pool never accumulates immortal
 * payloads. Without a pool the cache is transparent: every call produces a fresh
 * payload.
 */
final readonly class ViewResponseCache
{
    public const int DEFAULT_TTL_SECONDS = 86400; // 24 hours

    private const string JSON_KEY_PREFIX = 'chamber_orchestra.view.json.';
    private const string NORMALIZED_KEY_PREFIX = 'chamber_orchestra.view.norm.';

    public function __construct(
        private ?CacheItemPoolInterface $pool = null,
        private int $defaultTtl = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    /**
     * Returns the cached JSON for the signature, producing and storing it on a miss.
     *
     * @param int|null           $ttl     lifetime in seconds; null falls back to the configured default
     * @param \Closure(): string $produce
     */
    public function get(string $signature, ?int $ttl, \Closure $produce): string
    {
        if (null === $this->pool) {
            return $produce();
        }

        // Signatures may contain characters reserved by PSR-6, so hash them into a safe key
        $item = $this->pool->getItem(self::JSON_KEY_PREFIX.\hash('xxh128', $signature));

        if ($item->isHit() && \is_string($json = $item->get())) {
            return $json;
        }

        $json = $produce();

        $item->set($json);
        $item->expiresAfter($ttl ?? $this->defaultTtl);

        $this->pool->save($item);

        return $json;
    }

    /**
     * Returns the cached normalized payload for the signature, producing and storing it on a miss.
     *
     * Used for per-item caching (e.g. CachedView elements inside collections) where the
     * payload is normalized data rather than an encoded JSON string.
     *
     * @param int|null          $ttl     lifetime in seconds; null falls back to the configured default
     * @param \Closure(): mixed $produce
     */
    public function getNormalized(string $signature, ?int $ttl, \Closure $produce): mixed
    {
        if (null === $this->pool) {
            return $produce();
        }

        $item = $this->pool->getItem(self::NORMALIZED_KEY_PREFIX.\hash('xxh128', $signature));

        if ($item->isHit()) {
            return $item->get();
        }

        $payload = $produce();

        $item->set($payload);
        $item->expiresAfter($ttl ?? $this->defaultTtl);

        $this->pool->save($item);

        return $payload;
    }
}
