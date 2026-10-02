<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Support\FailureClassifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FailureClassifierTest extends TestCase
{
    #[Test]
    public function counts_configured_http_statuses(): void
    {
        $classifier = new FailureClassifier(CircuitBreakerConfig::defaults());

        $this->assertTrue($classifier->fromHttpStatus(503));
        $this->assertFalse($classifier->fromHttpStatus(404));
        $this->assertFalse($classifier->fromHttpStatus(500));
    }

    #[Test]
    public function can_count_http_500_when_enabled(): void
    {
        $classifier = new FailureClassifier(CircuitBreakerConfig::fromArray([
            'count_http_500' => true,
        ]));

        $this->assertTrue($classifier->fromHttpStatus(500));
    }

    #[Test]
    public function counts_timeout_and_connection_exceptions(): void
    {
        $classifier = new FailureClassifier(CircuitBreakerConfig::defaults());

        $this->assertTrue($classifier->fromException(new RuntimeException('Connection timed out')));
        $this->assertTrue($classifier->fromException(new RuntimeException('Connection refused')));
        $this->assertFalse($classifier->fromException(new RuntimeException('validation failed')));
    }
}
