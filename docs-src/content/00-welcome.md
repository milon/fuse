---
title: Welcome
---

# Welcome

Fuse is a circuit breaker for PHP 8.2+. You wrap a call that talks to another system. When that system starts failing, Fuse stops calling it for a short time, then lets a few trial calls through to see if it has recovered.

The breaker does not know about HTTP clients. You pass it a callable. Optional adapters cover [Saloon](https://docs.saloon.dev/) connectors and Laravel applications. Each adapter is independent, so the package runs with neither, with either, or with both.

## What you get

- Three states: **closed**, **open**, and **half-open**.
- A failure window, so a handful of old errors does not open the circuit forever.
- Half-open probes, so recovery is tested with a few calls instead of a flood.
- Pluggable storage: memory, Laravel cache, a database table, or any PSR-16 cache.
- `CircuitOpenException` when a call is rejected, with storage key and approximate retry-after.
- `FuseFactory` for shared store, named breaker overrides, and events outside Laravel.
- Optional Saloon traits, Laravel facade / `fuse()` helper, Artisan commands, and PHPUnit helpers.

## Where to go next

| You want to… | Read |
| --- | --- |
| Install it | [Installation](01-installation.html) |
| Understand the states | [How it works](02-how-it-works.html) |
| Call it from plain PHP | [Plain PHP](04-plain-php.html) |
| Use it in Laravel | [Laravel](07-laravel.html) |
| Protect a Saloon connector | [Saloon](09-saloon.html) |
| Laravel + Saloon together | [Laravel and Saloon](10-laravel-and-saloon.html) |
| See every combination, end to end | [Scenarios](11-scenarios.html) |
| Write tests | [Testing](13-testing.html) |

Start with [How it works](02-how-it-works.html) if the words closed, open, and half-open are new. Start with [Installation](01-installation.html) if you already know circuit breakers and want the package in a project.
