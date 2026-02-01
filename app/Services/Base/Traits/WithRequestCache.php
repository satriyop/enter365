<?php

declare(strict_types=1);

namespace App\Services\Base\Traits;

/**
 * Request-level caching for services.
 *
 * Caches computed values for the duration of a single request,
 * avoiding redundant calculations within the same HTTP lifecycle.
 *
 * This is NOT persistent caching — the cache is GC'd when the
 * service instance is destroyed (end of request).
 */
trait WithRequestCache
{
    /** @var array<string, mixed> */
    private array $requestCache = [];

    /**
     * Get or compute a cached value for this request.
     */
    protected function cached(string $key, callable $callback): mixed
    {
        if (! array_key_exists($key, $this->requestCache)) {
            $this->requestCache[$key] = $callback();
        }

        return $this->requestCache[$key];
    }

    /**
     * Invalidate a specific cache key.
     */
    protected function forgetCached(string $key): void
    {
        unset($this->requestCache[$key]);
    }

    /**
     * Clear all cached values.
     */
    protected function clearRequestCache(): void
    {
        $this->requestCache = [];
    }
}
