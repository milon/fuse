---
title: Laravel and Saloon
---

# Laravel and Saloon

Use the Laravel store so every connector, job, and controller shares one circuit. Require both packages in the application. The provider is discovered on its own.

```shell
composer require milon/fuse saloonphp/saloon
```

## Connector

Add the trait. That is the whole connector-side setup. Store, config, and events come from `FuseManager`, including any `breakers.billing` override.

```php
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Saloon\Http\Connector;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function resolveBaseUrl(): string
    {
        return 'https://billing.example.com';
    }
}
```

The default name strips a trailing `Connector` and kebab-cases the rest (`BillingConnector` → `billing`, `BillingSdkConnector` → `billing-sdk`). That name is what `breakers.billing` matches. Override `resolveCircuitBreakerName()` only when you want a different key.

## Request

Add the request trait so each operation gets its own circuit. `ChargeRequest` → operation `charge`:

```php
use Milon\Fuse\Saloon\Traits\HasCircuitBreakerOperation;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class ChargeRequest extends Request
{
    use HasCircuitBreakerOperation;

    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/charge';
    }
}
```

Optional override: `protected string $circuitBreakerOperation = 'checkout';`.

Together, a 503 from this request opens `fuse:billing:charge` with the numbers from `breakers.billing`.

## Call site

```php
use Milon\Fuse\CircuitOpenException;

public function __invoke(BillingConnector $billing)
{
    try {
        return $billing->send(new ChargeRequest($this->amount));
    } catch (CircuitOpenException $exception) {
        return response('Billing is unavailable.', 503)
            ->header('Retry-After', (string) ($exception->retryAfterSeconds ?? 0));
    }
}
```

## Same circuit from a job

A queued job that uses the facade, and a connector that uses the same store and name, share the snapshot:

```php
use Milon\Fuse\Laravel\Facades\Fuse;

Fuse::for('billing', operation: 'charge')
    ->run(fn () => $this->legacyClient->charge($amount));

// or: fuse('billing', operation: 'charge')->run(...)
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

## Ops and tests

```shell
php artisan fuse:status billing --operation=charge
php artisan fuse:reset billing --operation=charge --force
```

For PHPUnit + Saloon mocks, see [Testing](13-testing.html).
