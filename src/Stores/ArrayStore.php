<?php

declare(strict_types=1);

namespace Milon\Fuse\Stores;

use Milon\Fuse\Contracts\CircuitBreakerStore;

final class ArrayStore implements CircuitBreakerStore
{
    /** @var array<string, array{state: array, expires_at: int|null}> */
    private array $items = [];

    public function get(string $key): ?array
    {
        if (! isset($this->items[$key])) {
            return null;
        }

        $item = $this->items[$key];

        if ($item['expires_at'] !== null && $item['expires_at'] < time()) {
            unset($this->items[$key]);

            return null;
        }

        return $item['state'];
    }

    public function put(string $key, array $state, int $ttlSeconds): void
    {
        $this->items[$key] = [
            'state' => $state,
            'expires_at' => $ttlSeconds > 0 ? time() + $ttlSeconds : null,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    public function flush(): void
    {
        $this->items = [];
    }
}
