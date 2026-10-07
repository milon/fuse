<?php

declare(strict_types=1);

namespace Milon\Fuse\Events;

use Milon\Fuse\CircuitState;

final class CircuitOpened
{
    public function __construct(
        public readonly string $name,
        public readonly string $storageKey,
        public readonly ?string $operation,
        public readonly ?string $app,
        public readonly CircuitState $previous,
        public readonly string $reason,
    ) {}
}
