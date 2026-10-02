<?php

declare(strict_types=1);

namespace Milon\Fuse;

use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Support\KeyGenerator;

final class CircuitBreaker
{
    private readonly string $storageKey;

    private readonly KeyGenerator $keys;

    private readonly Clock $clock;

    public function __construct(
        private readonly string $name,
        private readonly CircuitBreakerStore $store,
        private readonly CircuitBreakerConfig $config = new CircuitBreakerConfig,
        private readonly ?string $operation = null,
        private readonly ?string $app = null,
        ?Clock $clock = null,
    ) {
        $this->keys = new KeyGenerator($this->config->keyPrefix, $this->app);
        $this->storageKey = $this->keys->make($this->name, $this->operation);
        $this->clock = $clock ?? new SystemClock;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function state(): CircuitState
    {
        return $this->hydrate()->state;
    }

    public function allowRequest(): bool
    {
        $snapshot = $this->hydrate();

        return match ($snapshot->state) {
            CircuitState::Closed => true,
            CircuitState::Open => $this->maybeTransitionToHalfOpen($snapshot),
            CircuitState::HalfOpen => $this->reserveHalfOpenProbe($snapshot),
        };
    }

    public function recordSuccess(): void
    {
        $snapshot = $this->hydrate();

        if ($snapshot->state === CircuitState::HalfOpen) {
            $successes = $snapshot->halfOpenSuccesses + 1;
            $inflight = max(0, $snapshot->halfOpenInflight - 1);

            if ($successes >= $this->config->halfOpenProbes) {
                $this->persist($this->closedState());

                return;
            }

            $this->persist(new CircuitSnapshot(
                state: CircuitState::HalfOpen,
                openedAt: $snapshot->openedAt,
                failures: [],
                halfOpenSuccesses: $successes,
                halfOpenInflight: $inflight,
            ));

            return;
        }

        $this->persist($this->closedState());
    }

    /**
     * Release a reserved half-open probe without counting success or failure
     * (e.g. business 4xx that should not affect the breaker).
     */
    public function releaseProbe(): void
    {
        $snapshot = $this->hydrate();

        if ($snapshot->state !== CircuitState::HalfOpen) {
            return;
        }

        $this->persist(new CircuitSnapshot(
            state: CircuitState::HalfOpen,
            openedAt: $snapshot->openedAt,
            failures: [],
            halfOpenSuccesses: $snapshot->halfOpenSuccesses,
            halfOpenInflight: max(0, $snapshot->halfOpenInflight - 1),
        ));
    }

    public function recordFailure(): void
    {
        $now = $this->clock->now();
        $snapshot = $this->hydrate();

        if ($snapshot->state === CircuitState::HalfOpen) {
            $this->persist(new CircuitSnapshot(
                state: CircuitState::Open,
                openedAt: $now,
                failures: [$now],
                halfOpenSuccesses: 0,
                halfOpenInflight: 0,
            ));

            return;
        }

        $windowStart = $now - $this->config->failureWindowSeconds;
        $failures = array_values(array_filter(
            $snapshot->failures,
            static fn (int $ts): bool => $ts >= $windowStart,
        ));
        $failures[] = $now;

        if (count($failures) >= $this->config->failureThreshold) {
            $this->persist(new CircuitSnapshot(
                state: CircuitState::Open,
                openedAt: $now,
                failures: $failures,
                halfOpenSuccesses: 0,
                halfOpenInflight: 0,
            ));

            return;
        }

        $this->persist(new CircuitSnapshot(
            state: CircuitState::Closed,
            openedAt: null,
            failures: $failures,
            halfOpenSuccesses: 0,
            halfOpenInflight: 0,
        ));
    }

    public function forceOpen(): void
    {
        $this->persist(new CircuitSnapshot(
            state: CircuitState::Open,
            openedAt: $this->clock->now(),
            failures: [],
            halfOpenSuccesses: 0,
            halfOpenInflight: 0,
        ));
    }

    public function forceClosed(): void
    {
        $this->persist($this->closedState());
    }

    public function reset(): void
    {
        $this->store->forget($this->storageKey);
    }

    private function maybeTransitionToHalfOpen(CircuitSnapshot $snapshot): bool
    {
        $openedAt = $snapshot->openedAt ?? $this->clock->now();

        if ($this->clock->now() < ($openedAt + $this->config->openSeconds)) {
            return false;
        }

        return $this->reserveHalfOpenProbe(new CircuitSnapshot(
            state: CircuitState::HalfOpen,
            openedAt: $openedAt,
            failures: [],
            halfOpenSuccesses: 0,
            halfOpenInflight: 0,
        ));
    }

    private function reserveHalfOpenProbe(CircuitSnapshot $snapshot): bool
    {
        if ($snapshot->halfOpenInflight >= $this->config->halfOpenProbes) {
            return false;
        }

        $this->persist(new CircuitSnapshot(
            state: CircuitState::HalfOpen,
            openedAt: $snapshot->openedAt,
            failures: [],
            halfOpenSuccesses: $snapshot->halfOpenSuccesses,
            halfOpenInflight: $snapshot->halfOpenInflight + 1,
        ));

        return true;
    }

    private function hydrate(): CircuitSnapshot
    {
        $raw = $this->store->get($this->storageKey);

        if ($raw === null) {
            return $this->closedState();
        }

        return CircuitSnapshot::fromArray($raw);
    }

    private function persist(CircuitSnapshot $snapshot): void
    {
        $ttl = max(
            $this->config->failureWindowSeconds,
            $this->config->openSeconds,
        ) * 2;

        $this->store->put($this->storageKey, $snapshot->toArray(), $ttl);
    }

    private function closedState(): CircuitSnapshot
    {
        return new CircuitSnapshot(
            state: CircuitState::Closed,
            openedAt: null,
            failures: [],
            halfOpenSuccesses: 0,
            halfOpenInflight: 0,
        );
    }
}
