<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Cache\ArrayStore as CacheArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Saloon\Contracts\HasCircuitBreakerOperation;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
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
    #[Test]
    public function a_saloon_connector_uses_the_laravel_cache_and_named_config(): void
    {
        $manager = new FuseManager(new LaravelCacheStore(new CacheRepository(new CacheArrayStore)), [
            'failure_threshold' => 8,
            'breakers' => [
                'billing' => [
                    'failure_threshold' => 1,
                ],
            ],
        ]);

        $connector = new class($manager) extends Connector
        {
            use HasCircuitBreaker;

            public function __construct(private FuseManager $manager) {}

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
                return $this->manager->configFor($this->resolveCircuitBreakerName());
            }

            protected function resolveCircuitBreakerStore(): CircuitBreakerStore
            {
                return $this->manager->store();
            }
        };

        $connector->withMockClient(new MockClient([
            MockResponse::make(body: 'down', status: 503),
            MockResponse::make(body: 'ok', status: 200),
        ]));

        $request = new class extends Request implements HasCircuitBreakerOperation
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/charge';
            }

            public function resolveCircuitBreakerOperation(): string
            {
                return 'charge';
            }
        };

        $connector->send($request);

        $breaker = $manager->for('billing', operation: 'charge')->breaker();

        $this->assertSame(CircuitState::Open, $breaker->state());
        $this->assertSame('fuse:billing:charge', $breaker->storageKey());

        $this->expectException(CircuitOpenException::class);
        $connector->send($request);
    }
}
