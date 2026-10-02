---
title: Stores
---

# Stores

The breaker reads and writes a snapshot through `Milon\Fuse\Contracts\CircuitBreakerStore`. Four implementations ship with the package. Pick one and pass the same instance to every `Fuse` that should share state.

| Store | Survives the next request | Needs |
| --- | --- | --- |
| `ArrayStore` | No | Nothing |
| `Psr16Store` | Yes, if the cache does | `psr/simple-cache` |
| `LaravelCacheStore` | Yes, if the cache does | Laravel cache |
| `DatabaseStore` | Yes | Laravel database |

## ArrayStore

State lives in a private array on that object. Two `ArrayStore` instances do not see each other. A typical PHP-FPM request builds a new application and throws the object away, so the next request starts closed even if this one opened the circuit.

Use it for tests, for a CLI process that makes many calls, and for several calls inside one request. Do not use it as the production store for a web application. The circuit would never stay open across requests, which is the situation a circuit breaker is for.

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$store = new ArrayStore;
$fuse = Fuse::for('billing-sdk', $store);

$fuse->run(fn () => $client->charge($amount));

// Same process, same $store: the second call sees the first.
$again = Fuse::for('billing-sdk', $store);
$again->breaker()->state();
```

Entries carry an `expires_at` timestamp. `get()` drops an expired entry and returns null, which the breaker treats as closed.

## PSR-16

Any `Psr\SimpleCache\CacheInterface` works: Redis, Memcached, the Symfony cache, Laravel's PSR-16 wrapper. Install the interface if your cache package does not already provide it:

```shell
composer require psr/simple-cache
```

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\Psr16Store;

// $cache is Psr\SimpleCache\CacheInterface, shared by every request.
$store = new Psr16Store($cache);
$fuse = Fuse::for('billing-sdk', $store);

$fuse->run(fn () => $client->charge($amount));
```

`put()` calls `set($key, $snapshot, $ttl)`. A TTL of `0` is stored with no expiry (`null`). `get()` and `forget()` map straight onto the cache. A missing key, or a value that is not a snapshot array, is treated as closed.

## Laravel cache

Inside a Laravel application you usually do not construct this yourself. The [service provider](07-laravel.html) does, from `FUSE_STORE=cache` (the default).

If you want it explicitly:

```php
use Illuminate\Support\Facades\Cache;
use Milon\Fuse\Stores\LaravelCacheStore;

$store = new LaravelCacheStore(Cache::store(config('fuse.cache_store')));
```

`cache_store` / `FUSE_CACHE_STORE` pins a store name (`redis`, `database`, `memcached`). Leave it null to use the application's default cache store. A TTL of `0` uses `forever()`.

## Database

`DatabaseStore` writes one row per circuit. The provider wires it when `FUSE_STORE=database`. The table, the migration, and the publish command are in [Database](08-database.html).

```php
use Illuminate\Support\Facades\DB;
use Milon\Fuse\Stores\DatabaseStore;

$store = new DatabaseStore(
    DB::connection(config('fuse.database.connection')),
    config('fuse.database.table'),
);
```

`get()` deletes the row when `expires_at` is in the past, then returns null. `put()` updates the row if the key exists and inserts it otherwise. `forget()` deletes the row.

## Your own store

Implement the three methods. `get` returns the snapshot array or null. `put` stores it for `$ttl` seconds (`0` means keep it). `forget` removes it.

```php
use Milon\Fuse\Contracts\CircuitBreakerStore;

final class RedisStore implements CircuitBreakerStore
{
    public function get(string $key): ?array
    {
        $raw = $this->redis->get($key);

        if (! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function put(string $key, array $state, int $ttl): void
    {
        $payload = json_encode($state);

        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, $payload);

            return;
        }

        $this->redis->set($key, $payload);
    }

    public function forget(string $key): void
    {
        $this->redis->del($key);
    }
}
```

The snapshot array uses the keys `state`, `opened_at`, `failures`, `half_open_successes`, and `half_open_inflight`.
