<?php

declare(strict_types=1);

namespace Milon\Fuse;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
