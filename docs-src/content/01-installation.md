---
title: Installation
---

# Installation

Fuse requires PHP 8.2 or newer. It has no required Composer packages of its own. Laravel and Saloon are optional, and installing one does not install the other.

```shell
composer require milon/fuse
```

## Plain PHP

That one command is the whole install. Use `Fuse::for()` with a store. [ArrayStore](06-stores.html) works immediately and keeps state only for the current process. For anything that must survive the next request, use a [PSR-16 cache](06-stores.html) or, inside Laravel, the cache or database store.

## With Saloon, without Laravel

```shell
composer require milon/fuse saloonphp/saloon
```

Saloon 3 and 4 are both accepted (`^3.0|^4.0`). Add the `HasCircuitBreaker` trait to a connector and return a store from `resolveCircuitBreakerStore()`. The full connector is in [Saloon](09-saloon.html).

## With Laravel, without Saloon

Require the package inside the Laravel application:

```shell
composer require milon/fuse
```

Laravel discovers `Milon\Fuse\Laravel\FuseServiceProvider` from the package. You do not register it by hand unless discovery is disabled. The provider binds `FuseManager` and a `CircuitBreakerStore`, and merges `config/fuse.php`.

Laravel 11, 12, and 13 are supported. Laravel 13 itself requires PHP 8.3. Fuse still runs on PHP 8.2 when Laravel is not installed.

Publish the config when you want to change defaults:

```shell
php artisan vendor:publish --tag=fuse-config
```

The cache store works with no migration. The database store needs one. See [Database](08-database.html).

## With Laravel and Saloon

```shell
composer require milon/fuse saloonphp/saloon
```

The service provider still owns the shared store. The Saloon connector resolves that store from the container. The complete connector is in [Laravel and Saloon](10-laravel-and-saloon.html).

## What Composer installs for you

| You installed | What Fuse uses |
| --- | --- |
| `milon/fuse` only | Core breaker, `ArrayStore` |
| plus `saloonphp/saloon` | `HasCircuitBreaker` |
| plus Laravel (`illuminate/support`, `illuminate/cache`) | Service provider, `LaravelCacheStore` |
| plus Laravel's database component | `DatabaseStore` and the migration |

Those Illuminate packages are suggestions, not hard requirements. A Laravel application already has them. A plain PHP project does not receive them.
