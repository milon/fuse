<?php

declare(strict_types=1);

namespace Milon\Fuse;

enum CircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';
}
