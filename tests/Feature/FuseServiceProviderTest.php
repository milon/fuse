<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Cache\ArrayStore as CacheArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Laravel\FuseServiceProvider;
use Milon\Fuse\Stores\LaravelCacheStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FuseServiceProviderTest extends TestCase
{
    private FuseApplication $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new FuseApplication;
    }

    protected function tearDown(): void
    {
        unset(
            ServiceProvider::$publishes[FuseServiceProvider::class],
            ServiceProvider::$publishGroups['fuse-config'],
            ServiceProvider::$publishGroups['fuse-migrations'],
        );

        parent::tearDown();
    }

    #[Test]
    public function it_merges_package_defaults_and_keeps_existing_values(): void
    {
        $this->app->config->set('fuse', [
            'failure_threshold' => 3,
        ]);

        (new FuseServiceProvider($this->app))->register();

        $this->assertSame(3, $this->app->config->get('fuse.failure_threshold'));
        $this->assertSame(60, $this->app->config->get('fuse.failure_window_seconds'));
        $this->assertNull($this->app->config->get('fuse.cache_store'));
    }

    #[Test]
    public function it_binds_the_cache_store_and_manager(): void
    {
        $provider = new FuseServiceProvider($this->app);
        $provider->register();

        $this->app->config->set('fuse.cache_store', 'redis');

        $store = $this->app->make(CircuitBreakerStore::class);
        $manager = $this->app->make(FuseManager::class);

        $this->assertInstanceOf(LaravelCacheStore::class, $store);
        $this->assertSame($store, $manager->store());
        $this->assertSame($store, $this->app->make(CircuitBreakerStore::class));
        $this->assertSame(['redis'], $this->app->cache->requested);
        $this->assertSame(8, $manager->configFor()->failureThreshold);
    }

    #[Test]
    public function it_uses_the_default_cache_store_when_none_is_configured(): void
    {
        (new FuseServiceProvider($this->app))->register();

        $this->app->make(CircuitBreakerStore::class);

        $this->assertSame([null], $this->app->cache->requested);
    }

    #[Test]
    public function it_publishes_the_config_file_in_console(): void
    {
        $this->app->console = true;

        (new FuseServiceProvider($this->app))->boot();

        $published = FuseServiceProvider::pathsToPublish(FuseServiceProvider::class, 'fuse-config');

        $this->assertSame(
            [dirname(__DIR__, 2).'/config/fuse.php' => '/app/config/fuse.php'],
            $published,
        );
    }

    #[Test]
    public function it_does_not_publish_the_config_file_outside_the_console(): void
    {
        (new FuseServiceProvider($this->app))->boot();

        $this->assertSame([], FuseServiceProvider::pathsToPublish(FuseServiceProvider::class, 'fuse-config'));
    }

    #[Test]
    public function the_config_file_matches_the_package_defaults(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2).'/config/fuse.php';
        $fromFile = CircuitBreakerConfig::fromArray($config);
        $defaults = CircuitBreakerConfig::defaults();

        $this->assertSame($defaults->failureThreshold, $fromFile->failureThreshold);
        $this->assertSame($defaults->failureWindowSeconds, $fromFile->failureWindowSeconds);
        $this->assertSame($defaults->openSeconds, $fromFile->openSeconds);
        $this->assertSame($defaults->halfOpenProbes, $fromFile->halfOpenProbes);
        $this->assertSame($defaults->countedHttpStatuses, $fromFile->countedHttpStatuses);
        $this->assertSame($defaults->countTimeouts, $fromFile->countTimeouts);
        $this->assertSame($defaults->countConnectionErrors, $fromFile->countConnectionErrors);
        $this->assertSame($defaults->countHttp500, $fromFile->countHttp500);
        $this->assertSame($defaults->keyPrefix, $fromFile->keyPrefix);
        $this->assertArrayHasKey('breakers', $config);
        $this->assertSame('cache', $config['store']);
        $this->assertSame('fuse_circuits', $config['database']['table'] ?? null);
    }

    #[Test]
    public function it_publishes_the_migration_in_console(): void
    {
        $this->app->console = true;

        (new FuseServiceProvider($this->app))->boot();

        $published = FuseServiceProvider::pathsToPublish(FuseServiceProvider::class, 'fuse-migrations');

        $this->assertSame(
            [dirname(__DIR__, 2).'/database/migrations' => '/app/database/migrations'],
            $published,
        );
    }

    #[Test]
    public function the_database_store_requires_a_database_manager(): void
    {
        (new FuseServiceProvider($this->app))->register();
        $this->app->config->set('fuse.store', 'database');
        $this->app->instance('db', new \stdClass);

        $this->expectException(RuntimeException::class);
        $this->app->make(CircuitBreakerStore::class);
    }
}

final class FuseApplication
{
    public FuseConfigRepository $config;

    public FuseCacheFactory $cache;

    public bool $console = false;

    /** @var array<string, callable(): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function __construct()
    {
        $this->config = new FuseConfigRepository;
        $this->cache = new FuseCacheFactory;
    }

    public function make(string $abstract): mixed
    {
        if ($abstract === 'config') {
            return $this->config;
        }

        if ($abstract === 'cache') {
            return $this->cache;
        }

        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        return $this->instances[$abstract] = ($this->bindings[$abstract])();
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function runningInConsole(): bool
    {
        return $this->console;
    }

    public function configPath(string $path = ''): string
    {
        return '/app/config/'.ltrim($path, '/');
    }

    public function databasePath(string $path = ''): string
    {
        return '/app/database/'.ltrim($path, '/');
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
}

final class FuseConfigRepository implements ConfigRepository
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function has($key): bool
    {
        return $this->get($key) !== null;
    }

    public function get($key, $default = null): mixed
    {
        if (! is_string($key)) {
            return $default;
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function set($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $name => $item) {
                $this->set((string) $name, $item);
            }

            return;
        }

        $segments = explode('.', (string) $key);
        $target = &$this->items;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $target[$segment] = $value;

                return;
            }

            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }
    }

    public function prepend($key, $value): void {}

    public function push($key, $value): void {}
}

final class FuseCacheFactory implements CacheFactory
{
    /** @var list<string|null> */
    public array $requested = [];

    private CacheContract $repository;

    public function __construct()
    {
        $this->repository = new CacheRepository(new CacheArrayStore);
    }

    public function store($name = null): CacheContract
    {
        $this->requested[] = is_string($name) ? $name : null;

        return $this->repository;
    }
}
