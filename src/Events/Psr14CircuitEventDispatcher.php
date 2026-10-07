<?php

declare(strict_types=1);

namespace Milon\Fuse\Events;

use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Bridges Fuse circuit events into a PSR-14 dispatcher.
 */
final class Psr14CircuitEventDispatcher implements CircuitEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
