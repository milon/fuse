# Changelog

## 1.0.0 - 2026-10-02

First stable release of the circuit breaker for PHP 8.2+.

- Closed, open, and half-open states, with a failure window and half-open probes
- `Fuse::for()` and `run()`, including `isFailure` and `isSuccess`
- `ArrayStore`, `Psr16Store`, `LaravelCacheStore`, and `DatabaseStore`
- Optional Laravel service provider, `config/fuse.php`, named breakers, and the `fuse_circuits` migration
- Optional Saloon `HasCircuitBreaker` adapter, including a separate circuit per request operation
