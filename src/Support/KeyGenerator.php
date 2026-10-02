<?php

declare(strict_types=1);

namespace Milon\Fuse\Support;

final class KeyGenerator
{
    public function __construct(
        private readonly string $prefix = 'fuse',
        private readonly ?string $app = null,
    ) {}

    public function make(string $name, ?string $operation = null): string
    {
        $parts = array_filter([
            $this->prefix,
            $this->app,
            $name,
            $operation,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return implode(':', $parts);
    }
}
