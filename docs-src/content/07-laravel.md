---
title: Laravel
---

# Laravel

In a Laravel application the package is a service provider, a config file, and `FuseManager`. You do not register the provider yourself. Composer discovery loads `Milon\Fuse\Laravel\FuseServiceProvider`.

```shell
composer require milon/fuse
php artisan vendor:publish --tag=fuse-config
```

Publishing is optional. Without it, the package config is still merged, and the defaults from [Configuration](03-configuration.html) apply.

## Resolve a fuse

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Laravel\FuseManager;

$fuse = app(FuseManager::class)->for('billing-sdk');

try {
    $invoice = $fuse->run(fn () => $this->billing->charge($amount));
} catch (CircuitOpenException) {
    $invoice = $this->queueForLater($amount);
}
```

`for()` reads the shared config, then overlays `config('fuse.breakers.billing-sdk')` when that entry is an array. The store is the singleton bound by the provider, so every `for('billing-sdk')` in every request shares one circuit.

```php
$fuse = app(FuseManager::class)->for(
    name: 'billing',
    operation: 'charge',
    app: 'punt',
);
```

That key is `fuse:punt:billing:charge` when `key_prefix` is `fuse`.

## Named breakers

Keep strict numbers on the dependency that cannot afford a long outage, and leave everyone else on the defaults:

```php
return [
    'store' => env('FUSE_STORE', 'cache'),
    'cache_store' => env('FUSE_CACHE_STORE'),
    'key_prefix' => env('FUSE_KEY_PREFIX', 'fuse'),

    'failure_threshold' => 8,
    'failure_window_seconds' => 60,
    'open_seconds' => 30,
    'half_open_probes' => 2,
    'counted_http_statuses' => [502, 503, 504],
    'count_timeouts' => true,
    'count_connection_errors' => true,
    'count_http_500' => false,

    'breakers' => [
        'billing-sdk' => [
            'failure_threshold' => 3,
            'open_seconds' => 15,
        ],
    ],
];
```

Read the merged config without building a fuse:

```php
app(FuseManager::class)->configFor('billing-sdk')->failureThreshold; // 3
app(FuseManager::class)->configFor('search')->failureThreshold;       // 8
app(FuseManager::class)->configFor()->openSeconds;                     // 30
```

## Cache store

`FUSE_STORE=cache` is the default. The provider resolves `Illuminate\Contracts\Cache\Repository`. Set `FUSE_CACHE_STORE=redis` to pin a store from `config/cache.php`. Leave `FUSE_CACHE_STORE` empty to use the default cache.

An open circuit stored in Redis is visible to the next PHP request and to every server that uses that Redis.

## Container bindings

| Binding | What you get |
| --- | --- |
| `Milon\Fuse\Contracts\CircuitBreakerStore` | `LaravelCacheStore` or `DatabaseStore` |
| `Milon\Fuse\Laravel\FuseManager` | The manager, as a singleton |

`Fuse` itself is not a singleton. Each `for()` returns a new fuse bound to the shared store and the config for that name.

## When discovery is off

Add the provider to `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

```php
Milon\Fuse\Laravel\FuseServiceProvider::class,
```

## An unknown store name

`FUSE_STORE=redis` does not select the Redis cache. `store` is only `cache` or `database`. Redis is selected with `FUSE_STORE=cache` and `FUSE_CACHE_STORE=redis`. Any other `store` value throws `RuntimeException`: `Fuse store [redis] is not supported.`
