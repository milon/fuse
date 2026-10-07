# Changelog

## 1.1.0 - 2026-10-07

Developer-experience release: less wiring for Laravel and Saloon, a shared factory for plain PHP, events, ops commands, richer open exceptions, and testing helpers.

### Added

- Framework-agnostic `FuseFactory` for a shared store, named breaker overrides, and events (`FuseManager` extends it)
- Laravel `Fuse` facade and `fuse()` helper for resolving named circuits
- Circuit state-change events (`CircuitOpened`, `CircuitClosed`, `CircuitHalfOpened`) with a Laravel dispatcher
- `CallableCircuitEventDispatcher` and `Psr14CircuitEventDispatcher` for non-Laravel listeners
- Artisan `fuse:status` and `fuse:reset` commands (`--operation`, `--app`, `--force`)
- Saloon request trait `HasCircuitBreakerOperation` (`ChargeRequest` → `charge`; optional `$circuitBreakerOperation`)
- Testing helpers: `FuseMockClient`, `FuseAssertions`, and `InteractsWithFuse`

### Changed

- Saloon `HasCircuitBreaker` resolves store, config, and events from a container-bound `FuseFactory` / `FuseManager`, so a Laravel connector only needs the trait
- Default Saloon circuit names strip a trailing `Connector` and kebab-case the rest (`BillingConnector` → `billing`)
- `CircuitOpenException` includes `storageKey`, `operation`, `app`, and approximate `retryAfterSeconds`
- Documentation updated for the new APIs (including a Testing chapter)

## 1.0.0 - 2026-10-02

First stable release of the circuit breaker for PHP 8.2+.

- Closed, open, and half-open states, with a failure window and half-open probes
- `Fuse::for()` and `run()`, including `isFailure` and `isSuccess`
- `ArrayStore`, `Psr16Store`, `LaravelCacheStore`, and `DatabaseStore`
- Optional Laravel service provider, `config/fuse.php`, named breakers, and the `fuse_circuits` migration
- Optional Saloon `HasCircuitBreaker` adapter, including a separate circuit per request operation
