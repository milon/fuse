<?php

declare(strict_types=1);

namespace Milon\Fuse;

use InvalidArgumentException;

final class CircuitBreakerConfig
{
    /**
     * @param list<int> $countedHttpStatuses
     */
    public function __construct(
        public readonly int $failureThreshold = 8,
        public readonly int $failureWindowSeconds = 60,
        public readonly int $openSeconds = 30,
        public readonly int $halfOpenProbes = 2,
        public readonly array $countedHttpStatuses = [502, 503, 504],
        public readonly bool $countTimeouts = true,
        public readonly bool $countConnectionErrors = true,
        public readonly bool $countHttp500 = false,
        public readonly string $keyPrefix = 'fuse',
    ) {
        if ($this->failureThreshold < 1) {
            throw new InvalidArgumentException('failureThreshold must be >= 1');
        }

        if ($this->failureWindowSeconds < 1) {
            throw new InvalidArgumentException('failureWindowSeconds must be >= 1');
        }

        if ($this->openSeconds < 1) {
            throw new InvalidArgumentException('openSeconds must be >= 1');
        }

        if ($this->halfOpenProbes < 1) {
            throw new InvalidArgumentException('halfOpenProbes must be >= 1');
        }
    }

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $statuses = $config['counted_http_statuses'] ?? [502, 503, 504];

        if (! is_array($statuses)) {
            $statuses = [502, 503, 504];
        }

        /** @var list<int> $normalizedStatuses */
        $normalizedStatuses = array_values(array_map(
            static fn (mixed $status): int => (int) $status,
            $statuses,
        ));

        if (($config['count_http_500'] ?? false) && ! in_array(500, $normalizedStatuses, true)) {
            $normalizedStatuses[] = 500;
        }

        return new self(
            failureThreshold: (int) ($config['failure_threshold'] ?? 8),
            failureWindowSeconds: (int) ($config['failure_window_seconds'] ?? 60),
            openSeconds: (int) ($config['open_seconds'] ?? 30),
            halfOpenProbes: (int) ($config['half_open_probes'] ?? 2),
            countedHttpStatuses: $normalizedStatuses,
            countTimeouts: (bool) ($config['count_timeouts'] ?? true),
            countConnectionErrors: (bool) ($config['count_connection_errors'] ?? true),
            countHttp500: (bool) ($config['count_http_500'] ?? false),
            keyPrefix: (string) ($config['key_prefix'] ?? 'fuse'),
        );
    }
}
