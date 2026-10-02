<?php

declare(strict_types=1);

namespace Milon\Fuse\Stores;

use Illuminate\Contracts\Cache\Repository;
use Milon\Fuse\Contracts\CircuitBreakerStore;

final class LaravelCacheStore implements CircuitBreakerStore
{
    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function get(string $key): ?array
    {
        $value = $this->cache->get($key);

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $state, int $ttlSeconds): void
    {
        if ($ttlSeconds > 0) {
            $this->cache->put($key, $state, $ttlSeconds);

            return;
        }

        $this->cache->forever($key, $state);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }
}
