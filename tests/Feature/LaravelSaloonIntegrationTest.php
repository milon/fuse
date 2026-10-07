<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Cache\ArrayStore as CacheArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\Saloon\Traits\HasCircuitBreakerOperation;
use Milon\Fuse\Stores\LaravelCacheStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

final class LaravelSaloonIntegrationTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        Container::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    #[Test]
    public function a_saloon_connector_uses_the_laravel_store_and_named_config_from_the_trait_alone(): void
    {
        $manager = new FuseManager(new LaravelCacheStore(new CacheRepository(new CacheArrayStore)), [
            'failure_threshold' => 8,
            'breakers' => [
                'billing' => [
                    'failure_threshold' => 1,
                ],
            ],
        ]);

        $this->container->instance(FuseManager::class, $manager);

        $connector = new class extends Connector
        {
            use HasCircuitBreaker;

            public function resolveBaseUrl(): string
            {
                return 'https://example.test';
            }

            protected function resolveCircuitBreakerName(): string
            {
                return 'billing';
            }
        };

        $connector->withMockClient(new MockClient([
            MockResponse::make(body: 'down', status: 503),
            MockResponse::make(body: 'ok', status: 200),
        ]));

        $request = new class extends Request
        {
            use HasCircuitBreakerOperation;

            protected Method $method = Method::GET;

            public function __construct()
            {
                $this->circuitBreakerOperation = 'charge';
            }

            public function resolveEndpoint(): string
            {
                return '/charge';
            }
        };

        $connector->send($request);

        $breaker = $manager->for('billing', operation: 'charge')->breaker();

        $this->assertSame(CircuitState::Open, $breaker->state());
        $this->assertSame('fuse:billing:charge', $breaker->storageKey());

        $this->expectException(CircuitOpenException::class);
        $connector->send($request);
    }

    #[Test]
    public function a_saloon_connector_without_a_store_explains_how_to_fix_it(): void
    {
        $connector = new class extends Connector
        {
            use HasCircuitBreaker;

            public function resolveBaseUrl(): string
            {
                return 'https://example.test';
            }
        };

        $connector->withMockClient(new MockClient([
            MockResponse::make(body: 'ok', status: 200),
        ]));

        $request = new class extends Request
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/ping';
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No circuit breaker store is available');

        $connector->send($request);
    }
}
