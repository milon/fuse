---
title: Saloon
---

# Saloon

The Saloon adapter is a trait on the connector and two pieces of middleware on every request. It works without Laravel. Install both packages:

```shell
composer require milon/fuse saloonphp/saloon
```

## A connector

Without Laravel you must return a store. The other methods have defaults: the circuit name is derived from the class (`BillingConnector` → `billing`), the config is `CircuitBreakerConfig::defaults()`, and there is no app segment. In Laravel, skip the store method — see [Laravel and Saloon](10-laravel-and-saloon.html).

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\Stores\Psr16Store;
use Saloon\Http\Connector;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function __construct(private Psr\SimpleCache\CacheInterface $cache) {}

    public function resolveBaseUrl(): string
    {
        return 'https://billing.example.com';
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        return new Psr16Store($this->cache);
    }

    protected function resolveCircuitBreakerName(): string
    {
        return 'billing-sdk';
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        return new CircuitBreakerConfig(failureThreshold: 5, openSeconds: 15);
    }

    protected function resolveCircuitBreakerApp(): ?string
    {
        return 'punt';
    }
}
```

`ArrayStore` inside `resolveCircuitBreakerStore()` is only useful in a test or a long-running process. A new connector in the next web request would have a new empty store. Pass a cache that outlives the request.

## What the middleware does

On each `send()`:

1. The request middleware calls `allowRequest()`. If the circuit is open it throws `CircuitOpenException` and Saloon does not send.
2. If the circuit allows the call, Saloon sends it.
3. The response middleware records a failure when the status is in `counted_http_statuses` (and 500, when `count_http_500` is true). Other statuses record a success.
4. If sending throws, the fatal middleware records a failure for timeouts and connection errors, and releases the probe for anything else. The original exception is still thrown.

The middleware names are `fuse_circuit_breaker_request`, `fuse_circuit_breaker_response`, and `fuse_circuit_breaker_fatal`.

## An open circuit

```php
use Milon\Fuse\CircuitOpenException;

try {
    $response = $connector->send(new ChargeRequest);
} catch (CircuitOpenException $exception) {
    // Nothing was sent. Use $exception->storageKey and $exception->retryAfterSeconds.
}
```

## A separate circuit per request

Implement `HasCircuitBreakerOperation` on the request. The connector does not need to change.

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

`ChargeRequest` uses `fuse:punt:billing-sdk:charge`. A `RefundRequest` that returns `refund` uses a different key. A request that does not implement the interface uses the connector circuit, with no operation segment.

## Counted responses

A 503 records a failure and still returns the Saloon response. Your code can read the body. The circuit moves. A 422 records a success, because it is not in the default status list: the server answered, and the call was a client error.

A thrown connect timeout records a failure through the fatal middleware and then rethrows.

## Tests and mocks

Saloon runs its mock middleware before Fuse's request middleware. If you mock a sequence of responses and the last send is the one that should be rejected, leave a spare `MockResponse` on the connector. Otherwise Saloon throws `NoMockResponseFoundException` before Fuse can throw `CircuitOpenException`.
