# Changelog

## Unreleased

- Saloon `HasCircuitBreaker` resolves store and config from Laravel's `FuseManager` when the service provider is registered, so a connector only needs the trait (optionally a custom circuit name)
- Default Saloon circuit names strip a trailing `Connector` and kebab-case the rest (`BillingConnector` → `billing`)
- Laravel `Fuse` facade and `fuse()` helper for resolving named circuits
- Circuit state-change events (`CircuitOpened`, `CircuitClosed`, `CircuitHalfOpened`) with a Laravel dispatcher
- Artisan `fuse:status` and `fuse:reset` commands
- `CircuitOpenException` includes storage key, operation, app, and approximate `retryAfterSeconds`
- Saloon request trait `HasCircuitBreakerOperation` (`ChargeRequest` → `charge`; optional `$circuitBreakerOperation`)
- Testing helpers: `FuseMockClient`, `FuseAssertions`, and `InteractsWithFuse`
- Framework-agnostic `FuseFactory` for shared store, named breaker overrides, and events (`FuseManager` extends it)
- `CallableCircuitEventDispatcher` and `Psr14CircuitEventDispatcher` for non-Laravel event listeners

## 1.0.0 - 2026-10-02

First stable release of the circuit breaker for PHP 8.2+.

- Closed, open, and half-open states, with a failure window and half-open probes
- `Fuse::for()` and `run()`, including `isFailure` and `isSuccess`
- `ArrayStore`, `Psr16Store`, `LaravelCacheStore`, and `DatabaseStore`
- Optional Laravel service provider, `config/fuse.php`, named breakers, and the `fuse_circuits` migration
- Optional Saloon `HasCircuitBreaker` adapter, including a separate circuit per request operation
