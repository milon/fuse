<?php

declare(strict_types=1);

namespace Milon\Fuse\Contracts;

interface CircuitEventDispatcher
{
    public function dispatch(object $event): void;
}
