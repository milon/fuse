<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Events\CallableCircuitEventDispatcher;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Stores\ArrayStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FuseFactoryTest extends TestCase
{
    #[Test]
    public function it_applies_named_breaker_overrides_and_shares_the_store(): void
    {
        $store = new ArrayStore;
        $factory = new FuseFactory($store, [
            'failure_threshold' => 8,
            'breakers' => [
                'billing' => [
                    'failure_threshold' => 1,
                ],
            ],
        ]);

        $this->assertSame(8, $factory->configFor()->failureThreshold);
        $this->assertSame(1, $factory->configFor('billing')->failureThreshold);
        $this->assertSame(['billing'], $factory->breakerNames());
        $this->assertSame($store, $factory->store());

        $fuse = $factory->for('billing');
        $fuse->breaker()->forceOpen();

        $this->assertSame(
            CircuitState::Open,
            $factory->for('billing')->breaker()->state(),
        );
    }

    #[Test]
    public function it_passes_events_through_to_built_fuses(): void
    {
        $opened = [];
        $events = (new CallableCircuitEventDispatcher)
            ->listenFor(CircuitOpened::class, static function (CircuitOpened $event) use (&$opened): void {
                $opened[] = $event->name;
            });

        $factory = new FuseFactory(new ArrayStore, [
            'breakers' => [
                'billing' => ['failure_threshold' => 1],
            ],
        ], $events);

        $factory->for('billing', config: new CircuitBreakerConfig(failureThreshold: 1))
            ->breaker()
            ->forceOpen();

        $this->assertSame(['billing'], $opened);
    }
}
