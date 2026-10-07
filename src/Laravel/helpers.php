<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Fuse;
use Milon\Fuse\Laravel\FuseManager;

if (! function_exists('fuse')) {
    /**
     * Resolve the Fuse manager, or a named circuit when $name is given.
     */
    function fuse(
        ?string $name = null,
        ?string $operation = null,
        ?string $app = null,
        ?CircuitBreakerConfig $config = null,
    ): Fuse|FuseManager {
        $manager = Container::getInstance()->make(FuseManager::class);

        if ($name === null) {
            return $manager;
        }

        return $manager->for($name, $operation, $app, $config);
    }
}
