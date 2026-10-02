# milon/fuse

HTTP-client-agnostic **circuit breaker** for PHP 8.2+, with an optional [Saloon](https://docs.saloon.dev/) adapter.

## Install

```bash
composer require milon/fuse
```

Saloon is **optional**. Install it only if you use the Saloon trait:

```bash
composer require saloonphp/saloon
```

## Callable usage (no Saloon)

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for(
    name: 'billing-sdk',
    store: new ArrayStore(),
    config: CircuitBreakerConfig::defaults(),
);

$result = $fuse->run(
    execute: fn () => $client->charge($payload),
);
```

When the circuit is open, `run()` throws `Milon\Fuse\CircuitOpenException` and does **not** invoke `execute`.

## Saloon usage

```php
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Stores\ArrayStore; // or LaravelCacheStore
use Saloon\Http\Connector;

class ExampleConnector extends Connector
{
    use HasCircuitBreaker;

    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }

    protected function resolveCircuitBreakerName(): string
    {
        return 'example';
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        return CircuitBreakerConfig::defaults();
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        return new ArrayStore();
    }
}
```

## Defaults

Aligned with social-api ADR-002 (lenient):

| Setting | Default |
|---------|---------|
| Failure threshold | 8 |
| Failure window | 60s |
| Open duration | 30s |
| Half-open probes | 2 |
| Counted HTTP statuses | 502, 503, 504 |

## Development

```bash
composer install
composer test
```

## License

MIT
