<?php

declare(strict_types=1);

namespace Milon\Fuse\Saloon\Contracts;

interface HasCircuitBreakerOperation
{
    public function resolveCircuitBreakerOperation(): string;
}
