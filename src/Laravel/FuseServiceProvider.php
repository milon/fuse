<?php

declare(strict_types=1);

namespace Milon\Fuse\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Stores\LaravelCacheStore;
use RuntimeException;

class FuseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packageConfigPath(), 'fuse');

        $this->app->singleton(CircuitBreakerStore::class, fn (): CircuitBreakerStore => $this->makeStore());

        $this->app->singleton(FuseManager::class, fn (): FuseManager => $this->makeManager());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->packageConfigPath() => $this->app->configPath('fuse.php'),
            ], 'fuse-config');
        }
    }

    private function makeStore(): CircuitBreakerStore
    {
        $storeName = $this->settings()['cache_store'] ?? null;

        $cache = $this->app->make('cache');

        if (! $cache instanceof CacheFactory) {
            throw new RuntimeException('Fuse requires a Laravel cache factory.');
        }

        $repository = is_string($storeName) && $storeName !== ''
            ? $cache->store($storeName)
            : $cache->store();

        return new LaravelCacheStore($repository);
    }

    private function makeManager(): FuseManager
    {
        return new FuseManager($this->app->make(CircuitBreakerStore::class), $this->settings());
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $config = $this->app->make('config');

        if (! $config instanceof ConfigRepository) {
            throw new RuntimeException('Fuse requires a Laravel config repository.');
        }

        $settings = $config->get('fuse', []);

        return is_array($settings) ? $settings : [];
    }

    private function packageConfigPath(): string
    {
        return dirname(__DIR__, 2).'/config/fuse.php';
    }
}
