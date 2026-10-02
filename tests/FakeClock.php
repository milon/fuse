<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests;

use Milon\Fuse\Clock;

final class FakeClock implements Clock
{
    public function __construct(
        private int $now = 1_700_000_000,
    ) {}

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function set(int $timestamp): void
    {
        $this->now = $timestamp;
    }
}
