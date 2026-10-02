<?php

declare(strict_types=1);

namespace Milon\Fuse\Stores;

use Illuminate\Database\ConnectionInterface;
use JsonException;
use Milon\Fuse\Contracts\CircuitBreakerStore;

final class DatabaseStore implements CircuitBreakerStore
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'fuse_circuits',
    ) {}

    public function get(string $key): ?array
    {
        $row = $this->connection->table($this->table)->where('key', $key)->first();

        if (! is_object($row)) {
            return null;
        }

        $expiresAt = $row->expires_at ?? null;

        if ($expiresAt !== null && (int) $expiresAt < time()) {
            $this->forget($key);

            return null;
        }

        return $this->decode($row->payload ?? null);
    }

    public function put(string $key, array $state, int $ttlSeconds): void
    {
        $payload = json_encode($state, JSON_THROW_ON_ERROR);

        $this->connection->table($this->table)->updateOrInsert(
            ['key' => $key],
            [
                'payload' => $payload,
                'expires_at' => $ttlSeconds > 0 ? time() + $ttlSeconds : null,
            ],
        );
    }

    public function forget(string $key): void
    {
        $this->connection->table($this->table)->where('key', $key)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
