<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Tests\FakeClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircuitOpenExceptionTest extends TestCase
{
    #[Test]
    public function open_exception_includes_key_and_retry_after(): void
    {
        $clock = new FakeClock(1_700_000_000);
        $breaker = new CircuitBreaker(
            name: 'billing',
            store: new ArrayStore,
            config: new CircuitBreakerConfig(failureThreshold: 1, openSeconds: 30),
            operation: 'charge',
            app: 'punt',
            clock: $clock,
        );

        $breaker->forceOpen();
        $clock->advance(10);

        $exception = $breaker->openException();

        $this->assertSame('billing', $exception->circuitName);
        $this->assertSame('fuse:punt:billing:charge', $exception->storageKey);
        $this->assertSame('charge', $exception->operation);
        $this->assertSame('punt', $exception->app);
        $this->assertSame(20, $exception->retryAfterSeconds);
        $this->assertSame(
            'Circuit [billing] is open; key [fuse:punt:billing:charge]; retry after approximately 20s',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function retry_after_is_zero_when_cooldown_has_elapsed_but_probes_are_full(): void
    {
        $clock = new FakeClock;
        $breaker = new CircuitBreaker(
            name: 'billing',
            store: new ArrayStore,
            config: new CircuitBreakerConfig(failureThreshold: 1, openSeconds: 5, halfOpenProbes: 1),
            clock: $clock,
        );

        $breaker->forceOpen();
        $clock->advance(5);
        $this->assertTrue($breaker->allowRequest());
        $this->assertFalse($breaker->allowRequest());

        $exception = $breaker->openException();

        $this->assertSame(0, $exception->retryAfterSeconds);
        $this->assertStringContainsString('cooldown elapsed', $exception->getMessage());
    }
}
