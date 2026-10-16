---
title: Testing
---

# Testing

Fuse ships helpers under `Milon\Fuse\Testing` for PHPUnit and Saloon. They are optional; use them when you want less boilerplate around open circuits and mocks.

## Assert and control state

`InteractsWithFuse` adds methods to a PHPUnit test case. `FuseAssertions` is the same API as static methods.

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Fuse;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Testing\InteractsWithFuse;
use PHPUnit\Framework\TestCase;

final class BillingCircuitTest extends TestCase
{
    use InteractsWithFuse;

    public function test_force_open_rejects_calls(): void
    {
        $fuse = Fuse::for(
            'billing',
            new ArrayStore,
            new CircuitBreakerConfig(failureThreshold: 1),
        );

        $this->assertCircuitClosed($fuse);
        $this->forceCircuitOpen($fuse);
        $this->assertCircuitOpen($fuse);

        $this->resetCircuit($fuse);
        $this->assertCircuitClosed($fuse);
    }

    public function test_named_circuit_via_factory(): void
    {
        $factory = new FuseFactory(new ArrayStore);

        $this->forceCircuitOpenFor($factory, 'billing', operation: 'charge');
        $this->assertCircuitOpenFor($factory, 'billing', operation: 'charge');
    }
}
```

| Method | What it does |
| --- | --- |
| `assertCircuitOpen` / `Closed` / `HalfOpen` | Assert state on a `Fuse` or `CircuitBreaker` |
| `assertCircuitOpenFor` / `ClosedFor` | Same, via `FuseFactory` / `FuseManager` + name |
| `forceCircuitOpen` / `forceCircuitClosed` / `resetCircuit` | Mutate state in a test |
| `forceCircuitOpenFor` / `resetCircuitFor` | Mutate a named circuit on a factory |

## Saloon mocks and open rejects

Saloon's mock middleware runs **before** Fuse. A `send()` that should throw `CircuitOpenException` still needs a spare `MockResponse` on the sequence, or Saloon throws `NoMockResponseFoundException` first.

`FuseMockClient::mock()` appends that spare for you (default one spare, status 200):

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\Saloon\Traits\HasCircuitBreakerOperation;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Testing\FuseMockClient;
use Milon\Fuse\Testing\InteractsWithFuse;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

final class BillingConnectorTest extends TestCase
{
    use InteractsWithFuse;

    public function test_open_circuit_rejects_without_hitting_the_network(): void
    {
        $factory = new FuseFactory(new ArrayStore, [
            'breakers' => [
                'billing' => ['failure_threshold' => 1],
            ],
        ]);

        $connector = new class($factory) extends Connector
        {
            use HasCircuitBreaker;

            public function __construct(private FuseFactory $factory) {}

            public function resolveBaseUrl(): string
            {
                return 'https://billing.example.com';
            }

            protected function resolveCircuitBreakerStore(): CircuitBreakerStore
            {
                return $this->factory->store();
            }

            protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
            {
                return $this->factory->configFor($this->resolveCircuitBreakerName());
            }
        };

        FuseMockClient::mock($connector, [
            MockResponse::make(body: 'down', status: 503),
        ]);

        $connector->send(new ChargeRequest);
        $this->assertCircuitOpenFor($factory, 'billing', operation: 'charge');

        $this->expectException(CircuitOpenException::class);
        $connector->send(new ChargeRequest);
    }
}

final class ChargeRequest extends Request
{
    use HasCircuitBreakerOperation;

    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/charge';
    }
}
```

`FuseMockClient::sequence($responses, openRejectSpares: 2)` builds a `MockClient` without attaching it. Pass `spare:` when the padded response should not be a 200.

In Laravel tests, `assertCircuitOpenFor(app(FuseManager::class), 'billing')` works the same way because `FuseManager` extends `FuseFactory`.
