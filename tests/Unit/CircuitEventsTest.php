<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Milon\Fuse\Events\CircuitClosed;
use Milon\Fuse\Events\CircuitHalfOpened;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Tests\FakeClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircuitEventsTest extends TestCase
{
    #[Test]
    public function it_dispatches_events_only_when_state_changes(): void
    {
        $events = new RecordingCircuitEventDispatcher;
        $clock = new FakeClock;
        $breaker = new CircuitBreaker(
            name: 'billing',
            store: new ArrayStore,
            config: new CircuitBreakerConfig(failureThreshold: 2, openSeconds: 5, halfOpenProbes: 1),
            operation: 'charge',
            clock: $clock,
            events: $events,
        );

        $breaker->recordFailure();
        $this->assertSame([], $events->all);

        $breaker->recordFailure();
        $this->assertCount(1, $events->all);
        $this->assertInstanceOf(CircuitOpened::class, $events->all[0]);
        $this->assertSame('billing', $events->all[0]->name);
        $this->assertSame('fuse:billing:charge', $events->all[0]->storageKey);
        $this->assertSame('charge', $events->all[0]->operation);
        $this->assertSame(CircuitState::Closed, $events->all[0]->previous);
        $this->assertSame('failure_threshold', $events->all[0]->reason);

        $clock->advance(5);
        $this->assertTrue($breaker->allowRequest());
        $this->assertInstanceOf(CircuitHalfOpened::class, $events->all[1]);
        $this->assertSame('cooldown_elapsed', $events->all[1]->reason);

        $breaker->recordSuccess();
        $this->assertInstanceOf(CircuitClosed::class, $events->all[2]);
        $this->assertSame('probes_succeeded', $events->all[2]->reason);
        $this->assertSame(CircuitState::HalfOpen, $events->all[2]->previous);
    }

    #[Test]
    public function reset_dispatches_closed_when_leaving_open(): void
    {
        $events = new RecordingCircuitEventDispatcher;
        $breaker = new CircuitBreaker(
            name: 'billing',
            store: new ArrayStore,
            config: new CircuitBreakerConfig(failureThreshold: 1),
            events: $events,
        );

        $breaker->forceOpen();
        $breaker->reset();

        $this->assertInstanceOf(CircuitOpened::class, $events->all[0]);
        $this->assertInstanceOf(CircuitClosed::class, $events->all[1]);
        $this->assertSame('reset', $events->all[1]->reason);
    }
}

final class RecordingCircuitEventDispatcher implements CircuitEventDispatcher
{
    /** @var list<object> */
    public array $all = [];

    public function dispatch(object $event): void
    {
        $this->all[] = $event;
    }
}
