---
title: Configuration
---

# Configuration

Pass a `CircuitBreakerConfig` when you build a fuse, or let Laravel read `config/fuse.php`. The same numbers mean the same thing in both places.

## Defaults

The defaults are lenient. A brief blip does not open the circuit, and HTTP 500 does not count unless you opt in.

| Setting | Default | What it does |
| --- | --- | --- |
| `failure_threshold` | 8 | Counted failures inside the window that open the circuit |
| `failure_window_seconds` | 60 | How long a failure stays in that count |
| `open_seconds` | 30 | How long calls are rejected after opening |
| `half_open_probes` | 2 | Probe slots in flight, and successes required to close |
| `counted_http_statuses` | 502, 503, 504 | Saloon responses that count as failures |
| `count_timeouts` | true | Exceptions that look like a timeout |
| `count_connection_errors` | true | Exceptions that look like a connection or TLS failure |
| `count_http_500` | false | When true, 500 is added to the counted statuses |
| `key_prefix` | `fuse` | First segment of every storage key |

Every numeric setting must be at least 1. `CircuitBreakerConfig` throws `InvalidArgumentException` otherwise.

## In PHP

Named arguments override one setting and leave the rest at the defaults:

```php
use Milon\Fuse\CircuitBreakerConfig;

$config = new CircuitBreakerConfig(
    failureThreshold: 5,
    failureWindowSeconds: 30,
    openSeconds: 15,
    halfOpenProbes: 1,
    countedHttpStatuses: [502, 503, 504],
    countTimeouts: true,
    countConnectionErrors: true,
    countHttp500: false,
    keyPrefix: 'fuse',
);
```

`CircuitBreakerConfig::defaults()` is `new CircuitBreakerConfig` with no arguments. `CircuitBreakerConfig::fromArray()` reads the snake_case keys from the table above. Unknown keys are ignored, which is why the Laravel file can also hold `store` and `breakers`.

## In Laravel

Publish the file, then edit `config/fuse.php`:

```shell
php artisan vendor:publish --tag=fuse-config
```

The published file is the same shape as the package default. Environment variables:

| Variable | Config key | Default |
| --- | --- | --- |
| `FUSE_STORE` | `store` | `cache` |
| `FUSE_CACHE_STORE` | `cache_store` | the application's default cache |
| `FUSE_DB_CONNECTION` | `database.connection` | the default database connection |
| `FUSE_KEY_PREFIX` | `key_prefix` | `fuse` |

`store` is `cache` or `database`. Anything else throws when the store is resolved.

### One circuit, different numbers

`breakers` overrides the shared defaults for a single name. Only the keys you set change. The rest stay on the shared defaults.

```php
'failure_threshold' => 8,
'open_seconds' => 30,

'breakers' => [
    'billing-sdk' => [
        'failure_threshold' => 3,
        'open_seconds' => 15,
    ],
],
```

`Fuse::for('billing-sdk')` and `Fuse::configFor('billing-sdk')` use threshold 3 and an open duration of 15 seconds. `for('other')` still uses 8 and 30. A non-array entry under `breakers` is ignored and the shared defaults apply.

An explicit config object always wins over the named override:

```php
use Milon\Fuse\Laravel\Facades\Fuse;

$fuse = Fuse::for(
    'billing-sdk',
    config: new CircuitBreakerConfig(failureThreshold: 1),
);
```
