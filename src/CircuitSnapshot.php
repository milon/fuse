<?php

declare(strict_types=1);

namespace Milon\Fuse;

/**
 * @phpstan-type SnapshotArray array{
 *   state: string,
 *   opened_at: int|null,
 *   failures: list<int>,
 *   half_open_successes: int,
 *   half_open_inflight: int,
 * }
 */
final class CircuitSnapshot
{
    /**
     * @param list<int> $failures
     */
    public function __construct(
        public readonly CircuitState $state,
        public readonly ?int $openedAt,
        public readonly array $failures,
        public readonly int $halfOpenSuccesses,
        public readonly int $halfOpenInflight,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $failures = $data['failures'] ?? [];

        if (! is_array($failures)) {
            $failures = [];
        }

        return new self(
            state: CircuitState::from((string) ($data['state'] ?? CircuitState::Closed->value)),
            openedAt: isset($data['opened_at']) ? (int) $data['opened_at'] : null,
            failures: array_values(array_map('intval', $failures)),
            halfOpenSuccesses: (int) ($data['half_open_successes'] ?? 0),
            halfOpenInflight: (int) ($data['half_open_inflight'] ?? 0),
        );
    }

    /**
     * @return SnapshotArray
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'opened_at' => $this->openedAt,
            'failures' => $this->failures,
            'half_open_successes' => $this->halfOpenSuccesses,
            'half_open_inflight' => $this->halfOpenInflight,
        ];
    }
}
