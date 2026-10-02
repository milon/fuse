<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Tests\FakeClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    #[Test]
    public function starts_closed_and_allows_requests(): void
    {
        $breaker = $this->breaker(threshold: 3);

        $this->assertSame(CircuitState::Closed, $breaker->state());
        $this->assertTrue($breaker->allowRequest());
    }

    #[Test]
    public function trips_open_after_threshold_failures_in_window(): void
    {
        $clock = new FakeClock;
        $breaker = $this->breaker(threshold: 3, clock: $clock);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertSame(CircuitState::Closed, $breaker->state());
        $this->assertTrue($breaker->allowRequest());

        $breaker->recordFailure();
        $this->assertSame(CircuitState::Open, $breaker->state());
        $this->assertFalse($breaker->allowRequest());
    }

    #[Test]
    public function does_not_trip_when_failures_fall_outside_window(): void
    {
        $clock = new FakeClock;
        $breaker = $this->breaker(
            threshold: 3,
            window: 10,
            clock: $clock,
        );

        $breaker->recordFailure();
        $breaker->recordFailure();
        $clock->advance(11);
        $breaker->recordFailure();

        $this->assertSame(CircuitState::Closed, $breaker->state());
        $this->assertTrue($breaker->allowRequest());
    }

    #[Test]
    public function open_transitions_to_half_open_after_cool_down(): void
    {
        $clock = new FakeClock;
        $breaker = $this->breaker(threshold: 2, openSeconds: 5, probes: 1, clock: $clock);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertFalse($breaker->allowRequest());

        $clock->advance(5);
        $this->assertTrue($breaker->allowRequest());
        $this->assertSame(CircuitState::HalfOpen, $breaker->state());
    }

    #[Test]
    public function half_open_successes_close_the_circuit(): void
    {
        $clock = new FakeClock;
        $breaker = $this->breaker(threshold: 2, openSeconds: 1, probes: 2, clock: $clock);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $clock->advance(1);

        $this->assertTrue($breaker->allowRequest());
        $breaker->recordSuccess();
        $this->assertSame(CircuitState::HalfOpen, $breaker->state());

        $this->assertTrue($breaker->allowRequest());
        $breaker->recordSuccess();
        $this->assertSame(CircuitState::Closed, $breaker->state());
    }

    #[Test]
    public function half_open_failure_reopens_the_circuit(): void
    {
        $clock = new FakeClock;
        $breaker = $this->breaker(threshold: 2, openSeconds: 1, probes: 2, clock: $clock);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $clock->advance(1);

        $this->assertTrue($breaker->allowRequest());
        $breaker->recordFailure();

        $this->assertSame(CircuitState::Open, $breaker->state());
        $this->assertFalse($breaker->allowRequest());
    }

    #[Test]
    public function limits_concurrent_half_open_probes(): void
    {
        $clock = new FakeClock;
        $breaker = $this->breaker(threshold: 1, openSeconds: 1, probes: 2, clock: $clock);

        $breaker->recordFailure();
        $clock->advance(1);

        $this->assertTrue($breaker->allowRequest());
        $this->assertTrue($breaker->allowRequest());
        $this->assertFalse($breaker->allowRequest());
    }

    #[Test]
    public function force_open_and_force_closed_override_state(): void
    {
        $breaker = $this->breaker();

        $breaker->forceOpen();
        $this->assertSame(CircuitState::Open, $breaker->state());
        $this->assertFalse($breaker->allowRequest());

        $breaker->forceClosed();
        $this->assertSame(CircuitState::Closed, $breaker->state());
        $this->assertTrue($breaker->allowRequest());
    }

    #[Test]
    public function reset_forgets_persisted_state(): void
    {
        $breaker = $this->breaker(threshold: 1);
        $breaker->recordFailure();
        $this->assertSame(CircuitState::Open, $breaker->state());

        $breaker->reset();
        $this->assertSame(CircuitState::Closed, $breaker->state());
    }

    #[Test]
    public function storage_key_includes_app_and_operation(): void
    {
        $breaker = new CircuitBreaker(
            name: 'paysafe',
            store: new ArrayStore,
            config: new CircuitBreakerConfig(keyPrefix: 'fuse'),
            operation: 'purchase',
            app: 'chanced',
        );

        $this->assertSame('fuse:chanced:paysafe:purchase', $breaker->storageKey());
    }

    private function breaker(
        int $threshold = 8,
        int $window = 60,
        int $openSeconds = 30,
        int $probes = 2,
        ?FakeClock $clock = null,
    ): CircuitBreaker {
        return new CircuitBreaker(
            name: 'test',
            store: new ArrayStore,
            config: new CircuitBreakerConfig(
                failureThreshold: $threshold,
                failureWindowSeconds: $window,
                openSeconds: $openSeconds,
                halfOpenProbes: $probes,
            ),
            clock: $clock,
        );
    }
}
