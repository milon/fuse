<?php

declare(strict_types=1);

namespace Milon\Fuse\Stores;

use Milon\Fuse\Contracts\CircuitBreakerStore;
use Psr\SimpleCache\CacheInterface;

final class Psr16Store implements CircuitBreakerStore
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function get(string $key): ?array
    {
        $value = $this->cache->get($key);

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $state, int $ttlSeconds): void
    {
        $this->cache->set($key, $state, $ttlSeconds > 0 ? $ttlSeconds : null);
    }

    public function forget(string $key): void
    {
        $this->cache->delete($key);
    }
}
