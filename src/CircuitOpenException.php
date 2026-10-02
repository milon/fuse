<?php

declare(strict_types=1);

namespace Milon\Fuse;

use Exception;
use Throwable;

final class CircuitOpenException extends Exception
{
    public function __construct(
        public readonly string $circuitName,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Circuit [{$circuitName}] is open",
            0,
            $previous,
        );
    }
}
