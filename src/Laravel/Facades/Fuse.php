<?php

declare(strict_types=1);

namespace Milon\Fuse\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Fuse as FuseInstance;
use Milon\Fuse\Laravel\FuseManager;

/**
 * @method static CircuitBreakerStore store()
 * @method static CircuitBreakerConfig configFor(?string $name = null)
 * @method static FuseInstance for(string $name, ?string $operation = null, ?string $app = null, ?CircuitBreakerConfig $config = null)
 *
 * @see FuseManager
 */
final class Fuse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FuseManager::class;
    }
}
