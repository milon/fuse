<?php

declare(strict_types=1);

namespace Milon\Fuse;

interface Clock
{
    public function now(): int;
}
