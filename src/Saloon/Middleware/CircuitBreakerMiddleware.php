<?php

declare(strict_types=1);

namespace Milon\Fuse\Saloon\Middleware;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Support\FailureClassifier;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Throwable;

final class CircuitBreakerMiddleware
{
    public const REQUEST_NAME = 'fuse_circuit_breaker_request';

    public const RESPONSE_NAME = 'fuse_circuit_breaker_response';

    public const FATAL_NAME = 'fuse_circuit_breaker_fatal';

    public function __construct(
        private readonly CircuitBreaker $breaker,
        private readonly FailureClassifier $classifier,
    ) {}

    public function __invoke(PendingRequest $pendingRequest): void
    {
        if (! $this->breaker->allowRequest()) {
            throw new CircuitOpenException($this->breaker->name());
        }

        $pendingRequest->middleware()->onResponse(
            callable: function (Response $response): Response {
                if ($this->classifier->fromHttpStatus($response->status())) {
                    $this->breaker->recordFailure();
                } else {
                    $this->breaker->recordSuccess();
                }

                return $response;
            },
            name: self::RESPONSE_NAME,
        );

        $pendingRequest->middleware()->onFatalException(
            callable: function (FatalRequestException $exception): void {
                $this->handleFatal($exception);
            },
            name: self::FATAL_NAME,
        );
    }

    public function handleFatal(Throwable $exception): void
    {
        $candidate = $exception->getPrevious() ?? $exception;

        if ($this->classifier->fromException($candidate)
            || $this->classifier->fromException($exception)) {
            $this->breaker->recordFailure();

            return;
        }

        $this->breaker->releaseProbe();
    }
}
