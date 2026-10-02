<p align="center">
  <img src="art/banner.svg" alt="milon/fuse — HTTP-client-agnostic circuit breaker for PHP" width="100%">
</p>

# milon/fuse

HTTP-client-agnostic **circuit breaker** for PHP 8.2+, with an optional [Saloon](https://docs.saloon.dev/) adapter.

## Install

```bash
composer require milon/fuse
```

Laravel and Saloon are **optional** and independent. The breaker itself only needs PHP, so it runs with neither, with either, or with both. Install Saloon only if you use the connector trait:

```bash
composer require saloonphp/saloon
```

## Callable usage (no Saloon)

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for(
    name: 'billing-sdk',
    store: new ArrayStore(),
    config: CircuitBreakerConfig::defaults(),
);

$result = $fuse->run(
    execute: fn () => $client->charge($payload),
);
```

When the circuit is open, `run()` throws `Milon\Fuse\CircuitOpenException` and does **not** invoke `execute`.

`ArrayStore` in that example is for tests and single-process scripts. A production web app should use a shared store, described below.

## Stores

The breaker reads and writes one snapshot per circuit. The store decides where that snapshot lives.

### ArrayStore

`ArrayStore` keeps the snapshot in a private array on the object. Use it when every call happens in the same process: a test, a local experiment, or a CLI command that makes several requests before it exits. Calls that share that instance see the same circuit.

**Do not use `ArrayStore` in a production web application.** PHP builds a new application for each request and throws it away when the response is sent. The next request gets a new empty store, so an open circuit is forgotten and traffic keeps hitting a failing dependency. Two `new ArrayStore()` instances do not share state either.

### Laravel cache

`LaravelCacheStore` writes the snapshot through Laravel's cache. This is the default when the service provider is registered. Every server that uses the same cache sees the same circuit, including on the next request.

### Database

`DatabaseStore` writes the snapshot to a `fuse_circuits` table. Use it when you want state to survive requests without depending on a cache. Publish the migration, run it, and select the database store:

```bash
php artisan vendor:publish --tag=fuse-migrations
php artisan migrate
```

```dotenv
FUSE_STORE=database
```

`FUSE_DB_CONNECTION` selects a connection. Leave it empty to use the default. The `database.table` config value must match the table created by the migration.

### PSR-16

`Psr16Store` wraps any PSR-16 cache. Install `psr/simple-cache` to use it.

## Saloon usage

```php
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Stores\ArrayStore; // tests only; production should use a shared store
use Saloon\Http\Connector;

class ExampleConnector extends Connector
{
    use HasCircuitBreaker;

    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }

    protected function resolveCircuitBreakerName(): string
    {
        return 'example';
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        return CircuitBreakerConfig::defaults();
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        return new ArrayStore();
    }
}
```

## Laravel

In a Laravel application the service provider is discovered automatically. It shares circuit state through the cache and reads `config/fuse.php`. Publish that file to change the defaults:

```bash
php artisan vendor:publish --tag=fuse-config
```

```php
use Milon\Fuse\Laravel\FuseManager;

$fuse = app(FuseManager::class)->for('billing-sdk');

$result = $fuse->run(
    execute: fn () => $client->charge($payload),
);
```

The provider uses the cache unless `FUSE_STORE=database`. Set `cache_store` to pin the cache driver. Add a `breakers` entry to override the defaults for one circuit name. The database store needs the migration from the Stores section.

A Saloon connector can take the same store and config from the container:

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Laravel\FuseManager;

protected function resolveCircuitBreakerStore(): CircuitBreakerStore
{
    return app(CircuitBreakerStore::class);
}

protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
{
    return app(FuseManager::class)->configFor($this->resolveCircuitBreakerName());
}
```

If package discovery is disabled, register `Milon\Fuse\Laravel\FuseServiceProvider` yourself.

## Defaults

The default configuration is intentionally lenient:

| Setting | Default |
|---------|---------|
| Failure threshold | 8 |
| Failure window | 60s |
| Open duration | 30s |
| Half-open probes | 2 |
| Counted HTTP statuses | 502, 503, 504 |

## Development

```bash
composer install
composer test
```

## License

MIT
