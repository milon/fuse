<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\Saloon\Traits\HasCircuitBreakerOperation;
use Milon\Fuse\Stores\ArrayStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

final class SaloonIntegrationTest extends TestCase
{
    #[Test]
    public function saloon_connector_trips_and_rejects_when_open(): void
    {
        $store = new ArrayStore;
        $connector = new class($store) extends Connector
        {
            use HasCircuitBreaker;

            public function __construct(private CircuitBreakerStore $store) {}

            public function resolveBaseUrl(): string
            {
                return 'https://example.test';
            }

            protected function resolveCircuitBreakerName(): string
            {
                return 'example';
            }

            protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
            {
                return new CircuitBreakerConfig(failureThreshold: 2);
            }

            protected function resolveCircuitBreakerStore(): CircuitBreakerStore
            {
                return $this->store;
            }
        };

        $mockClient = new MockClient([
            MockResponse::make(body: 'down', status: 503),
            MockResponse::make(body: 'down', status: 503),
            MockResponse::make(body: 'ok', status: 200),
        ]);
        $connector->withMockClient($mockClient);

        $request = new class extends Request
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/ping';
            }
        };

        $connector->send($request);
        $connector->send($request);

        $breaker = new CircuitBreaker(
            name: 'example',
            store: $store,
            config: new CircuitBreakerConfig(failureThreshold: 2),
        );
        $this->assertSame(CircuitState::Open, $breaker->state());

        $this->expectException(CircuitOpenException::class);
        $connector->send($request);
    }

    #[Test]
    public function saloon_request_trait_derives_the_operation_from_the_class_name(): void
    {
        $store = new ArrayStore;
        $connector = new class($store) extends Connector
        {
            use HasCircuitBreaker;

            public function __construct(private CircuitBreakerStore $store) {}

            public function resolveBaseUrl(): string
            {
                return 'https://example.test';
            }

            protected function resolveCircuitBreakerName(): string
            {
                return 'billing';
            }

            protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
            {
                return new CircuitBreakerConfig(failureThreshold: 1);
            }

            protected function resolveCircuitBreakerStore(): CircuitBreakerStore
            {
                return $this->store;
            }
        };

        $connector->withMockClient(new MockClient([
            MockResponse::make(body: 'down', status: 503),
            MockResponse::make(body: 'ok', status: 200),
        ]));

        $connector->send(new ChargeRequest);

        $breaker = new CircuitBreaker(
            name: 'billing',
            store: $store,
            config: new CircuitBreakerConfig(failureThreshold: 1),
            operation: 'charge',
        );

        $this->assertSame(CircuitState::Open, $breaker->state());
        $this->assertSame('fuse:billing:charge', $breaker->storageKey());
    }

    #[Test]
    public function saloon_connector_counts_fatal_transport_failures(): void
    {
        $store = new ArrayStore;
        $connector = new class($store) extends Connector
        {
            use HasCircuitBreaker;

            public function __construct(private CircuitBreakerStore $store) {}

            public function resolveBaseUrl(): string
            {
                return 'https://example.test';
            }

            protected function resolveCircuitBreakerName(): string
            {
                return 'example-fatal';
            }

            protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
            {
                return new CircuitBreakerConfig(failureThreshold: 2);
            }

            protected function resolveCircuitBreakerStore(): CircuitBreakerStore
            {
                return $this->store;
            }
        };

        $request = new class extends Request
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/ping';
            }
        };

        $fatal = static fn (\Saloon\Http\PendingRequest $pendingRequest) => new \Saloon\Exceptions\Request\FatalRequestException(
            new \RuntimeException('cURL error 7: Failed to connect to example.test'),
            $pendingRequest,
        );

        $mockClient = new MockClient([
            MockResponse::make()->throw($fatal),
            MockResponse::make()->throw($fatal),
        ]);
        $connector->withMockClient($mockClient);

        try {
            $connector->send($request);
        } catch (\Saloon\Exceptions\Request\FatalRequestException) {
        }

        try {
            $connector->send($request);
        } catch (\Saloon\Exceptions\Request\FatalRequestException) {
        }

        $breaker = new CircuitBreaker(
            name: 'example-fatal',
            store: $store,
            config: new CircuitBreakerConfig(failureThreshold: 2),
        );
        $this->assertSame(CircuitState::Open, $breaker->state());
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
