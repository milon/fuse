<?php

declare(strict_types=1);

namespace Milon\Fuse\Saloon\Traits;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Saloon\Contracts\HasCircuitBreakerOperation;
use Milon\Fuse\Saloon\Middleware\CircuitBreakerMiddleware;
use Milon\Fuse\Support\FailureClassifier;
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
        $class = static::class;
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    protected function resolveCircuitBreakerConfig(): CircuitBreakerConfig
    {
        return CircuitBreakerConfig::defaults();
    }

    protected function resolveCircuitBreakerApp(): ?string
    {
        return null;
    }

    abstract protected function resolveCircuitBreakerStore(): CircuitBreakerStore;
}
