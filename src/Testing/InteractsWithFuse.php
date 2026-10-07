<?php

declare(strict_types=1);

namespace Milon\Fuse\Testing;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Laravel\FuseManager;

/**
 * PHPUnit helpers for asserting and controlling circuit state.
 *
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 */
trait InteractsWithFuse
{
    protected function assertCircuitState(CircuitBreaker|Fuse $target, CircuitState $expected): void
    {
        FuseAssertions::assertState($target, $expected);
    }

    protected function assertCircuitOpen(CircuitBreaker|Fuse $target): void
    {
        FuseAssertions::assertOpen($target);
    }

    protected function assertCircuitClosed(CircuitBreaker|Fuse $target): void
    {
        FuseAssertions::assertClosed($target);
    }

    protected function assertCircuitHalfOpen(CircuitBreaker|Fuse $target): void
    {
        FuseAssertions::assertHalfOpen($target);
    }

    protected function assertCircuitOpenFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::assertOpenFor($manager, $name, $operation, $app);
    }

    protected function assertCircuitClosedFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::assertClosedFor($manager, $name, $operation, $app);
    }

    protected function forceCircuitOpen(CircuitBreaker|Fuse $target): void
    {
        FuseAssertions::forceOpen($target);
    }

    protected function forceCircuitClosed(CircuitBreaker|Fuse $target): void
    {
        FuseAssertions::forceClosed($target);
    }

    protected function resetCircuit(CircuitBreaker|Fuse $target): void
    {
        FuseAssertions::reset($target);
    }

    protected function forceCircuitOpenFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::forceOpenFor($manager, $name, $operation, $app);
    }

    protected function resetCircuitFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::resetFor($manager, $name, $operation, $app);
    }
}
