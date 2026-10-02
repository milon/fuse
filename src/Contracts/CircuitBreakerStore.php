<?php

declare(strict_types=1);

namespace Milon\Fuse\Contracts;

interface CircuitBreakerStore
{
    /**
     * @return array{
     *   state: string,
     *   opened_at: int|null,
     *   failures: list<int>,
     *   half_open_successes: int,
     *   half_open_inflight: int,
     * }|null
     */
    public function get(string $key): ?array;

    /**
     * @param array{
     *   state: string,
     *   opened_at: int|null,
     *   failures: list<int>,
     *   half_open_successes: int,
     *   half_open_inflight: int,
     * } $state
     */
    public function put(string $key, array $state, int $ttlSeconds): void;

    public function forget(string $key): void;
}
