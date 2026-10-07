<?php

declare(strict_types=1);

namespace Milon\Fuse\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Milon\Fuse\Contracts\CircuitEventDispatcher;

final class LaravelCircuitEventDispatcher implements CircuitEventDispatcher
{
    public function __construct(
        private readonly ?Dispatcher $dispatcher = null,
    ) {}

    public function dispatch(object $event): void
    {
        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch($event);

            return;
        }

        if (function_exists('event')) {
            event($event);
        }
    }
}
