---
title: Laravel
---

# Laravel

In a Laravel application the package is a service provider, a config file, a `Fuse` facade, and a `fuse()` helper. You do not register the provider yourself. Composer discovery loads `Milon\Fuse\Laravel\FuseServiceProvider` and aliases the facade as `Fuse`.

```shell
composer require milon/fuse
php artisan vendor:publish --tag=fuse-config
```

Publishing is optional. Without it, the package config is still merged, and the defaults from [Configuration](03-configuration.html) apply.

## Resolve a fuse

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Laravel\Facades\Fuse;

try {
    $invoice = Fuse::for('billing-sdk')->run(fn () => $this->billing->charge($amount));
} catch (CircuitOpenException) {
    $invoice = $this->queueForLater($amount);
}
```

`fuse('billing-sdk')` is the same as `Fuse::for('billing-sdk')`. Call `fuse()` with no arguments when you need the manager (`configFor()`, `store()`).

`for()` reads the shared config, then overlays `config('fuse.breakers.billing-sdk')` when that entry is an array. The store is the singleton bound by the provider, so every `for('billing-sdk')` in every request shares one circuit.

```php
$fuse = Fuse::for(
    name: 'billing',
    operation: 'charge',
    app: 'punt',
);
```

That key is `fuse:punt:billing:charge` when `key_prefix` is `fuse`.

The facade class is `Milon\Fuse\Laravel\Facades\Fuse`. That is not the core `Milon\Fuse\Fuse` value object — import the facade (or use the `Fuse` alias / `fuse()` helper) in Laravel code.

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
Fuse::configFor('billing-sdk')->failureThreshold; // 3
Fuse::configFor('search')->failureThreshold;       // 8
Fuse::configFor()->openSeconds;                     // 30
```

## Cache store

`FUSE_STORE=cache` is the default. The provider resolves `Illuminate\Contracts\Cache\Repository`. Set `FUSE_CACHE_STORE=redis` to pin a store from `config/cache.php`. Leave `FUSE_CACHE_STORE` empty to use the default cache.

An open circuit stored in Redis is visible to the next PHP request and to every server that uses that Redis.

## Container bindings

| Binding | What you get |
| --- | --- |
| `Milon\Fuse\Contracts\CircuitBreakerStore` | `LaravelCacheStore` or `DatabaseStore` |
| `Milon\Fuse\Contracts\CircuitEventDispatcher` | Dispatches into Laravel's event system |
| `Milon\Fuse\Laravel\FuseManager` | The manager, as a singleton (also the `Fuse` facade root) |

Each `Fuse::for()` / `fuse('…')` returns a new fuse bound to the shared store and the config for that name.

## State-change events

When a circuit changes state, Fuse dispatches a plain event object through Laravel's dispatcher:

| Event | When |
| --- | --- |
| `Milon\Fuse\Events\CircuitOpened` | Closed or half-open → open |
| `Milon\Fuse\Events\CircuitHalfOpened` | Open → half-open (cooldown over) |
| `Milon\Fuse\Events\CircuitClosed` | Open or half-open → closed (including `reset()`) |

Each event carries `name`, `storageKey`, `operation`, `app`, `previous` (`CircuitState`), and `reason` (`failure_threshold`, `probe_failed`, `probes_succeeded`, `cooldown_elapsed`, `forced`, `reset`, …).

```php
use Illuminate\Support\Facades\Event;
use Milon\Fuse\Events\CircuitOpened;

Event::listen(CircuitOpened::class, function (CircuitOpened $event): void {
    logger()->warning('Circuit opened', [
        'key' => $event->storageKey,
        'reason' => $event->reason,
    ]);
});
```

Saloon connectors that use `HasCircuitBreaker` share the same dispatcher. Outside Laravel, pass a `CircuitEventDispatcher` into `Fuse::for(..., events: $dispatcher)`.

## When discovery is off

Add the provider to `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

```php
Milon\Fuse\Laravel\FuseServiceProvider::class,
```

## An unknown store name

`FUSE_STORE=redis` does not select the Redis cache. `store` is only `cache` or `database`. Redis is selected with `FUSE_STORE=cache` and `FUSE_CACHE_STORE=redis`. Any other `store` value throws `RuntimeException`: `Fuse store [redis] is not supported.`
