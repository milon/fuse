<?php

declare(strict_types=1);

namespace Milon\Fuse\Saloon\Traits;

use Milon\Fuse\Support\CircuitName;

/**
 * Add to a Saloon request so it gets its own circuit operation segment.
 *
 * Set {@see $circuitBreakerOperation} to override the name derived from the
 * class (`ChargeRequest` → `charge`).
 */
trait HasCircuitBreakerOperation
{
    protected string $circuitBreakerOperation = '';

    public function resolveCircuitBreakerOperation(): string
    {
        if ($this->circuitBreakerOperation !== '') {
            return $this->circuitBreakerOperation;
        }

        return CircuitName::fromRequestClass(static::class);
    }
}
