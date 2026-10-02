---
title: Laravel and Saloon
---

# Laravel and Saloon

Use the Laravel store so every connector, job, and controller shares one circuit. Require both packages in the application. The provider is discovered on its own.

```shell
composer require milon/fuse saloonphp/saloon
```

## Connector

Resolve the store and the config from `FuseManager`. The manager already applied `breakers.billing` if you defined it.

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Saloon\Http\Connector;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function resolveBaseUrl(): string
    {
        return 'https://billing.example.com';
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        return app(CircuitBreakerStore::class);
    }

    protected function resolveCircuitBreakerName(): string
    {
        return 'billing';
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        return app(FuseManager::class)->configFor('billing');
    }
}
```

`configFor('billing')` returns the merged `CircuitBreakerConfig`. A request that implements `HasCircuitBreakerOperation` still adds its operation to the key, so the config numbers are the billing numbers and the storage key can still be `fuse:billing:charge`.

## Request

```php
use Milon\Fuse\Saloon\Contracts\HasCircuitBreakerOperation;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class ChargeRequest extends Request implements HasCircuitBreakerOperation
{
    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/charge';
    }

    public function resolveCircuitBreakerOperation(): string
    {
        return 'charge';
    }
}
```

## Call site

```php
use Milon\Fuse\CircuitOpenException;

public function __invoke(BillingConnector $billing)
{
    try {
        return $billing->send(new ChargeRequest($this->amount));
    } catch (CircuitOpenException) {
        return response('Billing is unavailable.', 503);
    }
}
```

## Same circuit from a job

A queued job that uses the manager, and a connector that uses the same store and name, share the snapshot:

```php
use Milon\Fuse\Laravel\FuseManager;

app(FuseManager::class)
    ->for('billing', operation: 'charge')
    ->run(fn () => $this->legacyClient->charge($amount));
```

If the connector has just opened `fuse:billing:charge`, this `run()` throws `CircuitOpenException` and the legacy client is not called.

## Config that both paths see

```php
'breakers' => [
    'billing' => [
        'failure_threshold' => 3,
        'open_seconds' => 20,
        'counted_http_statuses' => [502, 503, 504],
    ],
],
```

`FUSE_STORE` still chooses cache or database for the whole application. The connector does not pick a different driver.
