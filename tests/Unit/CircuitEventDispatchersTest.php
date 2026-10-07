<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitState;
use Milon\Fuse\Events\CallableCircuitEventDispatcher;
use Milon\Fuse\Events\CircuitClosed;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\Events\Psr14CircuitEventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class CircuitEventDispatchersTest extends TestCase
{
    #[Test]
    public function callable_dispatcher_filters_by_event_class(): void
    {
        $opened = 0;
        $closed = 0;

        $dispatcher = (new CallableCircuitEventDispatcher)
            ->listenFor(CircuitOpened::class, static function () use (&$opened): void {
                $opened++;
            })
            ->listenFor(CircuitClosed::class, static function () use (&$closed): void {
                $closed++;
            });

        $dispatcher->dispatch(new CircuitOpened(
            name: 'billing',
            storageKey: 'fuse:billing',
            operation: null,
            app: null,
            previous: CircuitState::Closed,
            reason: 'forced',
        ));
        $dispatcher->dispatch(new CircuitClosed(
            name: 'billing',
            storageKey: 'fuse:billing',
            operation: null,
            app: null,
            previous: CircuitState::Open,
            reason: 'forced',
        ));

        $this->assertSame(1, $opened);
        $this->assertSame(1, $closed);
    }

    #[Test]
    public function psr14_dispatcher_forwards_to_the_psr_dispatcher(): void
    {
        $seen = [];

        $psr = new class($seen) implements EventDispatcherInterface
        {
            /** @param list<object> $seen */
            public function __construct(private array &$seen) {}

            public function dispatch(object $event): object
            {
                $this->seen[] = $event;

                return $event;
            }
        };

        $dispatcher = new Psr14CircuitEventDispatcher($psr);
        $event = new CircuitOpened(
            name: 'billing',
            storageKey: 'fuse:billing',
            operation: null,
            app: null,
            previous: CircuitState::Closed,
            reason: 'forced',
        );

        $dispatcher->dispatch($event);

        $this->assertSame([$event], $seen);
    }
}
