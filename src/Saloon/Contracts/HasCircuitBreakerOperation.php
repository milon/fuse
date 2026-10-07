<?php

declare(strict_types=1);

namespace Milon\Fuse\Saloon\Contracts;

/**
 * Optional contract for a custom operation resolver.
 *
 * Prefer {@see \Milon\Fuse\Saloon\Traits\HasCircuitBreakerOperation} on requests
 * unless you need a fully custom `resolveCircuitBreakerOperation()` implementation.
 */
interface HasCircuitBreakerOperation
{
    public function resolveCircuitBreakerOperation(): string;
}
