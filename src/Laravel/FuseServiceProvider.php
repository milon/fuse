<?php

declare(strict_types=1);

namespace Milon\Fuse\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\ServiceProvider;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Milon\Fuse\Stores\DatabaseStore;
use Milon\Fuse\Stores\LaravelCacheStore;
use RuntimeException;

class FuseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packageConfigPath(), 'fuse');

        $this->app->singleton(CircuitBreakerStore::class, fn (): CircuitBreakerStore => $this->makeStore());

        $this->app->singleton(CircuitEventDispatcher::class, fn (): CircuitEventDispatcher => $this->makeEventDispatcher());

        $this->app->singleton(FuseManager::class, fn (): FuseManager => $this->makeManager());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->packageConfigPath() => $this->app->configPath('fuse.php'),
            ], 'fuse-config');

            $this->publishesMigrations([
                dirname(__DIR__, 2).'/database/migrations' => $this->app->databasePath('migrations'),
            ], 'fuse-migrations');
        }
    }

    private function makeStore(): CircuitBreakerStore
    {
        $driver = $this->settings()['store'] ?? 'cache';

        if (! is_string($driver) || $driver === '' || $driver === 'cache') {
            return $this->makeCacheStore();
        }

        if ($driver === 'database') {
            return $this->makeDatabaseStore();
        }

        throw new RuntimeException("Fuse store [{$driver}] is not supported.");
    }

    private function makeCacheStore(): LaravelCacheStore
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

    private function makeDatabaseStore(): DatabaseStore
    {
        $database = $this->settings()['database'] ?? [];
        $connectionName = is_array($database) ? ($database['connection'] ?? null) : null;
        $table = is_array($database) ? ($database['table'] ?? 'fuse_circuits') : 'fuse_circuits';

        $db = $this->app->make('db');

        if (! $db instanceof ConnectionResolverInterface) {
            throw new RuntimeException('Fuse requires a Laravel database manager to use the database store.');
        }

        $connection = is_string($connectionName) && $connectionName !== ''
            ? $db->connection($connectionName)
            : $db->connection();

        return new DatabaseStore(
            $connection,
            is_string($table) && $table !== '' ? $table : 'fuse_circuits',
        );
    }

    private function makeEventDispatcher(): CircuitEventDispatcher
    {
        if ($this->app->bound(EventDispatcher::class)) {
            return new LaravelCircuitEventDispatcher($this->app->make(EventDispatcher::class));
        }

        if ($this->app->bound('events')) {
            $resolved = $this->app->make('events');

            return new LaravelCircuitEventDispatcher(
                $resolved instanceof EventDispatcher ? $resolved : null,
            );
        }

        return new LaravelCircuitEventDispatcher;
    }

    private function makeManager(): FuseManager
    {
        return new FuseManager(
            $this->app->make(CircuitBreakerStore::class),
            $this->settings(),
            $this->app->make(CircuitEventDispatcher::class),
        );
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
