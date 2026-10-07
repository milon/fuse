<?php

declare(strict_types=1);

namespace Milon\Fuse;

use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Milon\Fuse\Events\CircuitClosed;
use Milon\Fuse\Events\CircuitHalfOpened;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\Support\KeyGenerator;
use Throwable;

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
        private readonly ?CircuitEventDispatcher $events = null,
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

    public function operation(): ?string
    {
        return $this->operation;
    }

    public function app(): ?string
    {
        return $this->app;
    }

    public function state(): CircuitState
    {
        return $this->hydrate()->state;
    }

    /**
     * Seconds until an open circuit may accept a probe, or 0 when the cooldown
     * has already elapsed (including half-open with no free probe slot).
     * Null when the circuit is closed.
     */
    public function secondsUntilRetry(): ?int
    {
        $snapshot = $this->hydrate();

        return match ($snapshot->state) {
            CircuitState::Closed => null,
            CircuitState::HalfOpen => 0,
            CircuitState::Open => max(
                0,
                ($snapshot->openedAt ?? $this->clock->now()) + $this->config->openSeconds - $this->clock->now(),
            ),
        };
    }

    public function openException(?Throwable $previous = null): CircuitOpenException
    {
        return new CircuitOpenException(
            circuitName: $this->name,
            previous: $previous,
            storageKey: $this->storageKey,
            retryAfterSeconds: $this->secondsUntilRetry(),
            operation: $this->operation,
            app: $this->app,
        );
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
                $this->persist($this->closedState(), 'probes_succeeded');

                return;
            }

            $this->persist(new CircuitSnapshot(
                state: CircuitState::HalfOpen,
                openedAt: $snapshot->openedAt,
                failures: [],
                halfOpenSuccesses: $successes,
                halfOpenInflight: $inflight,
            ), 'probe_succeeded');

            return;
        }

        $this->persist($this->closedState(), 'success');
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
        ), 'probe_released');
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
            ), 'probe_failed');

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
            ), 'failure_threshold');

            return;
        }

        $this->persist(new CircuitSnapshot(
            state: CircuitState::Closed,
            openedAt: null,
            failures: $failures,
            halfOpenSuccesses: 0,
            halfOpenInflight: 0,
        ), 'failure_recorded');
    }

    public function forceOpen(): void
    {
        $this->persist(new CircuitSnapshot(
            state: CircuitState::Open,
            openedAt: $this->clock->now(),
            failures: [],
            halfOpenSuccesses: 0,
            halfOpenInflight: 0,
        ), 'forced');
    }

    public function forceClosed(): void
    {
        $this->persist($this->closedState(), 'forced');
    }

    public function reset(): void
    {
        $previous = $this->hydrate()->state;
        $this->store->forget($this->storageKey);

        if ($previous !== CircuitState::Closed) {
            $this->dispatchTransition($previous, CircuitState::Closed, 'reset');
        }
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
        ), 'cooldown_elapsed');
    }

    private function reserveHalfOpenProbe(CircuitSnapshot $snapshot, string $reason = 'probe_reserved'): bool
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
        ), $reason);

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

    private function persist(CircuitSnapshot $snapshot, string $reason): void
    {
        $previous = $this->hydrate();

        $ttl = max(
            $this->config->failureWindowSeconds,
            $this->config->openSeconds,
        ) * 2;

        $this->store->put($this->storageKey, $snapshot->toArray(), $ttl);

        if ($previous->state !== $snapshot->state) {
            $this->dispatchTransition($previous->state, $snapshot->state, $reason);
        }
    }

    private function dispatchTransition(CircuitState $previous, CircuitState $next, string $reason): void
    {
        if ($this->events === null) {
            return;
        }

        $event = match ($next) {
            CircuitState::Open => new CircuitOpened(
                name: $this->name,
                storageKey: $this->storageKey,
                operation: $this->operation,
                app: $this->app,
                previous: $previous,
                reason: $reason,
            ),
            CircuitState::Closed => new CircuitClosed(
                name: $this->name,
                storageKey: $this->storageKey,
                operation: $this->operation,
                app: $this->app,
                previous: $previous,
                reason: $reason,
            ),
            CircuitState::HalfOpen => new CircuitHalfOpened(
                name: $this->name,
                storageKey: $this->storageKey,
                operation: $this->operation,
                app: $this->app,
                previous: $previous,
                reason: $reason,
            ),
        };

        $this->events->dispatch($event);
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
