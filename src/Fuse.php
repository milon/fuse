<?php

declare(strict_types=1);

namespace Milon\Fuse;

use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Milon\Fuse\Support\FailureClassifier;
use Throwable;

final class Fuse
{
    private readonly FailureClassifier $classifier;

    public function __construct(
        private readonly CircuitBreaker $breaker,
        private readonly CircuitBreakerConfig $config,
    ) {
        $this->classifier = new FailureClassifier($this->config);
    }

    public static function for(
        string $name,
        CircuitBreakerStore $store,
        ?CircuitBreakerConfig $config = null,
        ?string $operation = null,
        ?string $app = null,
        ?Clock $clock = null,
        ?CircuitEventDispatcher $events = null,
    ): self {
        $config ??= CircuitBreakerConfig::defaults();

        return new self(
            new CircuitBreaker(
                name: $name,
                store: $store,
                config: $config,
                operation: $operation,
                app: $app,
                clock: $clock,
                events: $events,
            ),
            $config,
        );
    }

    public function breaker(): CircuitBreaker
    {
        return $this->breaker;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $execute
     * @param  (callable(Throwable): bool)|null  $isFailure
     * @param  (callable(T): bool)|null  $isSuccess
     * @return T
     *
     * @throws CircuitOpenException
     * @throws Throwable
     */
    public function run(callable $execute, ?callable $isFailure = null, ?callable $isSuccess = null): mixed
    {
        if (! $this->breaker->allowRequest()) {
            throw new CircuitOpenException($this->breaker->name());
        }

        try {
            $result = $execute();
        } catch (Throwable $exception) {
            $counts = $isFailure !== null
                ? $isFailure($exception)
                : $this->classifier->fromException($exception);

            if ($counts) {
                $this->breaker->recordFailure();
            } else {
                $this->breaker->releaseProbe();
            }

            throw $exception;
        }

        $successful = $isSuccess !== null ? $isSuccess($result) : true;

        if ($successful) {
            $this->breaker->recordSuccess();
        } else {
            $this->breaker->recordFailure();
        }

        return $result;
    }
}
