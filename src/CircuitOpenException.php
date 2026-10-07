<?php

declare(strict_types=1);

namespace Milon\Fuse;

use Exception;
use Throwable;

final class CircuitOpenException extends Exception
{
    public function __construct(
        public readonly string $circuitName,
        ?Throwable $previous = null,
        public readonly string $storageKey = '',
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $operation = null,
        public readonly ?string $app = null,
    ) {
        parent::__construct(
            $this->buildMessage(),
            0,
            $previous,
        );
    }

    private function buildMessage(): string
    {
        $parts = ["Circuit [{$this->circuitName}] is open"];

        if ($this->storageKey !== '') {
            $parts[] = "key [{$this->storageKey}]";
        }

        if ($this->retryAfterSeconds !== null) {
            $parts[] = $this->retryAfterSeconds === 0
                ? 'cooldown elapsed'
                : "retry after approximately {$this->retryAfterSeconds}s";
        }

        return implode('; ', $parts);
    }
}
