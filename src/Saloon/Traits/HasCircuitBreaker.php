<?php

declare(strict_types=1);

namespace Milon\Fuse\Saloon\Traits;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Saloon\Contracts\HasCircuitBreakerOperation;
use Milon\Fuse\Saloon\Middleware\CircuitBreakerMiddleware;
use Milon\Fuse\Support\CircuitName;
use Milon\Fuse\Support\FailureClassifier;
use RuntimeException;
use Saloon\Http\PendingRequest;

trait HasCircuitBreaker
{
    protected ?CircuitBreaker $fuseCircuitBreaker = null;

    protected ?CircuitBreakerMiddleware $fuseCircuitBreakerMiddleware = null;

    public function bootHasCircuitBreaker(PendingRequest $pendingRequest): void
    {
        $breaker = $this->fuseCircuitBreaker($pendingRequest);
        $middleware = $this->fuseCircuitBreakerMiddleware($breaker);

        $pendingRequest->middleware()->onRequest(
            callable: $middleware,
            name: CircuitBreakerMiddleware::REQUEST_NAME,
        );
    }

    protected function fuseCircuitBreaker(PendingRequest $pendingRequest): CircuitBreaker
    {
        $operation = null;
        $request = $pendingRequest->getRequest();

        if ($request instanceof HasCircuitBreakerOperation) {
            $operation = $request->resolveCircuitBreakerOperation();
        }

        $this->fuseCircuitBreaker = new CircuitBreaker(
            name: $this->resolveCircuitBreakerName(),
            store: $this->resolveCircuitBreakerStore(),
            config: $this->resolveCircuitBreakerConfig(),
            operation: $operation,
            app: $this->resolveCircuitBreakerApp(),
        );

        return $this->fuseCircuitBreaker;
    }

    protected function fuseCircuitBreakerMiddleware(CircuitBreaker $breaker): CircuitBreakerMiddleware
    {
        $this->fuseCircuitBreakerMiddleware = new CircuitBreakerMiddleware(
            $breaker,
            new FailureClassifier($this->resolveCircuitBreakerConfig()),
        );

        return $this->fuseCircuitBreakerMiddleware;
    }

    protected function resolveCircuitBreakerName(): string
    {
        return CircuitName::fromConnectorClass(static::class);
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        $manager = $this->resolveLaravelFuseManager();

        if ($manager !== null) {
            return $manager->configFor($this->resolveCircuitBreakerName());
        }

        return CircuitBreakerConfig::defaults();
    }

    protected function resolveCircuitBreakerApp(): ?string
    {
        return null;
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        $manager = $this->resolveLaravelFuseManager();

        if ($manager !== null) {
            return $manager->store();
        }

        throw new RuntimeException(
            'No circuit breaker store is available. Override resolveCircuitBreakerStore() on '
            .static::class
            .' or use Laravel with Milon\\Fuse\\Laravel\\FuseServiceProvider registered.',
        );
    }

    protected function resolveLaravelFuseManager(): ?FuseManager
    {
        if (! class_exists(FuseManager::class)) {
            return null;
        }

        $containerClass = 'Illuminate\\Container\\Container';

        if (! class_exists($containerClass)) {
            return null;
        }

        /** @var \Illuminate\Container\Container $container */
        $container = $containerClass::getInstance();

        if (! $container->bound(FuseManager::class)) {
            return null;
        }

        $manager = $container->make(FuseManager::class);

        return $manager instanceof FuseManager ? $manager : null;
    }
}
