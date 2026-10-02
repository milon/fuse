---
title: FAQ
---

# FAQ

## Does this send HTTP requests?

No. Fuse decides whether your callable, or your Saloon connector, is allowed to run. The HTTP client is yours.

## Will an open circuit stay open on the next web request?

Only if the store outlives the request. `ArrayStore` does not. Laravel's cache store, the database store, and a PSR-16 cache do, as long as every request uses that same cache or table and the same storage key.

## Do two servers share a circuit?

They do when they share the store: one Redis, one database, one Memcached. They do not when each machine has its own `ArrayStore` or its own file cache.

## Why did a 500 not open the circuit?

`count_http_500` defaults to false. 500 is often an application bug, and opening the circuit hides it. Set `count_http_500` to true, or add `500` to `counted_http_statuses`, when a 500 means the dependency is down.

## Why did a 422 not open the circuit?

422 is a client error. The dependency answered. The default counted statuses are 502, 503, and 504. Add 429 or anything else you want in `counted_http_statuses`.

## Why did my exception not count?

The classifier looks at the class name and the message. `card declined` does not match. A timeout or a connection error does. Pass `isFailure` when the rule is your own exception type.

## Can charge and refund fail independently?

Yes. Pass `operation: 'charge'` and `operation: 'refund'`, or implement `HasCircuitBreakerOperation` on the Saloon request. Different operations are different keys.

## What is the difference between forceClosed and reset?

`forceClosed()` writes a clean closed snapshot. `reset()` deletes the row or cache key. The next call behaves the same way in both cases: the circuit is closed and has no recorded failures.

## The database store says it needs a Laravel database manager

`FUSE_STORE=database` resolves `Illuminate\Database\ConnectionResolverInterface` from the container. That binding comes with `illuminate/database`. A Laravel application has it. Selecting the database driver in a project that does not will throw `Fuse requires a Laravel database manager to use the database store.`

## I set FUSE_STORE=redis and it threw

`store` accepts `cache` or `database`. Redis is a cache store:

```dotenv
FUSE_STORE=cache
FUSE_CACHE_STORE=redis
```

## Saloon threw NoMockResponseFoundException instead of CircuitOpenException

Saloon's mock middleware runs before Fuse. The open circuit never got to reject the call because the mock sequence was already empty. Leave one spare `MockResponse` on the connector for the send that should be rejected.

## Does the package load Laravel when I am not using Laravel?

No. The service provider is only loaded by Laravel's package discovery. `DatabaseStore` and `LaravelCacheStore` are only loaded when your code references them. A project that requires `milon/fuse` and nothing else does not install Illuminate.
