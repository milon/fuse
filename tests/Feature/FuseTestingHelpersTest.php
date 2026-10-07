<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Fuse;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\Stores\ArrayStore;
use Milon\Fuse\Testing\FuseAssertions;
use Milon\Fuse\Testing\FuseMockClient;
use Milon\Fuse\Testing\InteractsWithFuse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

final class FuseTestingHelpersTest extends TestCase
{
    use InteractsWithFuse;

    #[Test]
    public function assertions_and_force_helpers_work_on_fuse_and_manager(): void
    {
        $store = new ArrayStore;
        $fuse = Fuse::for('billing', $store, new CircuitBreakerConfig(failureThreshold: 1));
        $manager = new FuseManager($store, []);

        $this->assertCircuitClosed($fuse);
        $this->forceCircuitOpen($fuse);
        $this->assertCircuitOpen($fuse);
        $this->assertCircuitOpenFor($manager, 'billing');

        $this->resetCircuitFor($manager, 'billing');
        $this->assertCircuitClosedFor($manager, 'billing');

        FuseAssertions::forceOpenFor($manager, 'billing', operation: 'charge');
        FuseAssertions::assertOpenFor($manager, 'billing', operation: 'charge');
        FuseAssertions::assertState(
            $manager->for('billing', operation: 'charge'),
            CircuitState::Open,
        );
    }

    #[Test]
    public function mock_client_pads_a_spare_response_for_open_rejects(): void
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

        FuseMockClient::mock($connector, [
            MockResponse::make(body: 'down', status: 503),
        ]);

        $request = new class extends Request
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/ping';
            }
        };

        $connector->send($request);

        $this->assertCircuitOpen(Fuse::for('billing', $store, new CircuitBreakerConfig(failureThreshold: 1)));

        $this->expectException(CircuitOpenException::class);
        $connector->send($request);
    }
}
