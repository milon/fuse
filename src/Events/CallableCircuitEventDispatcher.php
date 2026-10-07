<?php

declare(strict_types=1);

namespace Milon\Fuse\Events;

use Milon\Fuse\Contracts\CircuitEventDispatcher;

/**
 * Lightweight dispatcher for plain PHP: listen with callables, no framework required.
 */
final class CallableCircuitEventDispatcher implements CircuitEventDispatcher
{
    /** @var list<callable(object): void> */
    private array $listeners = [];

    /**
     * @param  callable(object): void  ...$listeners
     */
    public function __construct(callable ...$listeners)
    {
        foreach ($listeners as $listener) {
            $this->listen($listener);
        }
    }

    /**
     * @param  callable(object): void  $listener
     */
    public function listen(callable $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }

    /**
     * @param  class-string  $event
     * @param  callable(object): void  $listener
     */
    public function listenFor(string $event, callable $listener): self
    {
        $this->listeners[] = static function (object $dispatched) use ($event, $listener): void {
            if ($dispatched instanceof $event) {
                $listener($dispatched);
            }
        };

        return $this;
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener($event);
        }
    }
}
