<?php

declare(strict_types=1);

namespace Milon\Fuse\Testing;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\FuseFactory;

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
        FuseFactory $factory,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::assertOpenFor($factory, $name, $operation, $app);
    }

    protected function assertCircuitClosedFor(
        FuseFactory $factory,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::assertClosedFor($factory, $name, $operation, $app);
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
        FuseFactory $factory,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::forceOpenFor($factory, $name, $operation, $app);
    }

    protected function resetCircuitFor(
        FuseFactory $factory,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        FuseAssertions::resetFor($factory, $name, $operation, $app);
    }
}
