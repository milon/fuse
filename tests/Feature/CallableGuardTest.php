<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Tests\FakeClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CallableGuardTest extends TestCase
{
    #[Test]
    public function run_returns_callable_result_and_stays_closed(): void
    {
        $fuse = Fuse::for('sdk', new ArrayStore, new CircuitBreakerConfig(failureThreshold: 2));

        $result = $fuse->run(fn (): string => 'ok');

        $this->assertSame('ok', $result);
        $this->assertSame(CircuitState::Closed, $fuse->breaker()->state());
    }

    #[Test]
    public function run_trips_open_and_blocks_later_calls(): void
    {
        $fuse = Fuse::for('sdk', new ArrayStore, new CircuitBreakerConfig(failureThreshold: 2));

        try {
            $fuse->run(function (): never {
                throw new RuntimeException('connection refused');
            });
        } catch (RuntimeException) {
        }

        try {
            $fuse->run(function (): never {
                throw new RuntimeException('connection refused');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(CircuitState::Open, $fuse->breaker()->state());

        $this->expectException(CircuitOpenException::class);
        $fuse->run(fn (): string => 'should not run');
    }

    #[Test]
    public function run_does_not_count_business_exceptions_by_default(): void
    {
        $fuse = Fuse::for('sdk', new ArrayStore, new CircuitBreakerConfig(failureThreshold: 1));

        try {
            $fuse->run(function (): never {
                throw new RuntimeException('validation failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(CircuitState::Closed, $fuse->breaker()->state());
        $this->assertTrue($fuse->breaker()->allowRequest());
    }

    #[Test]
    public function run_recovers_after_cool_down_probe_succeeds(): void
    {
        $clock = new FakeClock;
        $store = new ArrayStore;
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            openSeconds: 5,
            halfOpenProbes: 1,
        );
        $fuse = Fuse::for('sdk', $store, $config, clock: $clock);

        try {
            $fuse->run(function (): never {
                throw new RuntimeException('timed out');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse($fuse->breaker()->allowRequest());

        $clock->advance(5);
        $result = $fuse->run(fn (): string => 'recovered');

        $this->assertSame('recovered', $result);
        $this->assertSame(CircuitState::Closed, $fuse->breaker()->state());
    }
}
