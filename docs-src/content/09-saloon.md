---
title: Saloon
---

# Saloon

The Saloon adapter is a trait on the connector and middleware on every request. It works without Laravel. Install both packages:

```shell
composer require milon/fuse saloonphp/saloon
```

## A connector

### Minimal (defaults)

The circuit name is derived from the class (`BillingConnector` → `billing`). Config defaults to `CircuitBreakerConfig::defaults()`. You must still provide a store unless a `FuseFactory` / `FuseManager` is bound in Illuminate's container.

```php
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
}
```

### With FuseFactory (recommended outside Laravel)

Share store, named overrides, and events the same way Laravel does:

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Saloon\Http\Connector;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function __construct(private FuseFactory $fuse) {}

    public function resolveBaseUrl(): string
    {
        return 'https://billing.example.com';
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        return $this->fuse->store();
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        return $this->fuse->configFor($this->resolveCircuitBreakerName());
    }

    protected function resolveCircuitEventDispatcher(): ?CircuitEventDispatcher
    {
        return $this->fuse->events();
    }
}
```

If you bind `FuseFactory` (or Laravel's `FuseManager`) on `Illuminate\Container\Container::getInstance()`, you can omit those three methods — the trait resolves them automatically. In Laravel that binding already exists; see [Laravel and Saloon](10-laravel-and-saloon.html).

### Full overrides

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
    // Nothing was sent.
    // $exception->storageKey, $exception->retryAfterSeconds, $exception->circuitName
}
```

## A separate circuit per request

Add the request trait. The connector does not need to change. The operation defaults to the class name with a trailing `Request` stripped (`ChargeRequest` → `charge`).

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

Override the name when the derived value is wrong:

```php
class CheckoutRequest extends Request
{
    use HasCircuitBreakerOperation;

    protected string $circuitBreakerOperation = 'charge';

    // ...
}
```

The older `Milon\Fuse\Saloon\Contracts\HasCircuitBreakerOperation` interface still works if you prefer a custom `resolveCircuitBreakerOperation()` method.

With the connector examples above, `ChargeRequest` uses a key like `fuse:billing:charge` (or `fuse:punt:billing-sdk:charge` when you set `app` and a custom name). A `RefundRequest` uses a different key. A request without the trait or interface uses the connector circuit, with no operation segment.

## Counted responses

A 503 records a failure and still returns the Saloon response. Your code can read the body. The circuit moves. A 422 records a success, because it is not in the default status list: the server answered, and the call was a client error.

A thrown connect timeout records a failure through the fatal middleware and then rethrows.

## Tests and mocks

See [Testing](13-testing.html) for `FuseMockClient` and `InteractsWithFuse`. Short version: Saloon's mock middleware runs before Fuse, so pad a spare mock for open-circuit rejects — or let `FuseMockClient::mock()` do it:

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Testing\FuseMockClient;
use Milon\Fuse\Testing\InteractsWithFuse;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockResponse;

final class BillingConnectorTest extends TestCase
{
    use InteractsWithFuse;

    public function test_open_circuit_rejects(): void
    {
        /** @var BillingConnector $connector */
        /** @var FuseFactory $factory */

        FuseMockClient::mock($connector, [
            MockResponse::make(status: 503),
        ]);

        $connector->send(new ChargeRequest);
        $this->assertCircuitOpenFor($factory, 'billing', operation: 'charge');

        $this->expectException(CircuitOpenException::class);
        $connector->send(new ChargeRequest);
    }
}
```
