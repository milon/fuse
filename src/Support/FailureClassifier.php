<?php

declare(strict_types=1);

namespace Milon\Fuse\Support;

use Milon\Fuse\CircuitBreakerConfig;
use Throwable;

final class FailureClassifier
{
    public function __construct(
        private readonly CircuitBreakerConfig $config,
    ) {}

    public function fromHttpStatus(int $status): bool
    {
        if (in_array($status, $this->config->countedHttpStatuses, true)) {
            return true;
        }

        if ($this->config->countHttp500 && $status === 500) {
            return true;
        }

        return false;
    }

    public function fromException(Throwable $exception): bool
    {
        $class = $exception::class;
        $message = strtolower($exception->getMessage());

        if ($this->config->countTimeouts && $this->looksLikeTimeout($class, $message)) {
            return true;
        }

        if ($this->config->countConnectionErrors && $this->looksLikeConnectionError($class, $message)) {
            return true;
        }

        return false;
    }

    private function looksLikeTimeout(string $class, string $message): bool
    {
        if (str_contains(strtolower($class), 'timeout')) {
            return true;
        }

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'operation timed out');
    }

    private function looksLikeConnectionError(string $class, string $message): bool
    {
        if (str_contains(strtolower($class), 'connect')) {
            return true;
        }

        return str_contains($message, 'connection refused')
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'network is unreachable')
            || str_contains($message, 'ssl')
            || str_contains($message, 'tls');
    }
}
