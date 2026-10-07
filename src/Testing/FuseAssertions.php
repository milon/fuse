<?php

declare(strict_types=1);

namespace Milon\Fuse\Testing;

use Milon\Fuse\CircuitBreaker;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Laravel\FuseManager;
use PHPUnit\Framework\Assert;

final class FuseAssertions
{
    public static function assertState(CircuitBreaker|Fuse $target, CircuitState $expected): void
    {
        Assert::assertSame(
            $expected,
            self::breaker($target)->state(),
            'Unexpected circuit state.',
        );
    }

    public static function assertOpen(CircuitBreaker|Fuse $target): void
    {
        self::assertState($target, CircuitState::Open);
    }

    public static function assertClosed(CircuitBreaker|Fuse $target): void
    {
        self::assertState($target, CircuitState::Closed);
    }

    public static function assertHalfOpen(CircuitBreaker|Fuse $target): void
    {
        self::assertState($target, CircuitState::HalfOpen);
    }

    public static function assertOpenFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        self::assertOpen($manager->for($name, operation: $operation, app: $app));
    }

    public static function assertClosedFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        self::assertClosed($manager->for($name, operation: $operation, app: $app));
    }

    public static function forceOpen(CircuitBreaker|Fuse $target): void
    {
        self::breaker($target)->forceOpen();
    }

    public static function forceClosed(CircuitBreaker|Fuse $target): void
    {
        self::breaker($target)->forceClosed();
    }

    public static function reset(CircuitBreaker|Fuse $target): void
    {
        self::breaker($target)->reset();
    }

    public static function forceOpenFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        self::forceOpen($manager->for($name, operation: $operation, app: $app));
    }

    public static function resetFor(
        FuseManager $manager,
        string $name,
        ?string $operation = null,
        ?string $app = null,
    ): void {
        self::reset($manager->for($name, operation: $operation, app: $app));
    }

    private static function breaker(CircuitBreaker|Fuse $target): CircuitBreaker
    {
        return $target instanceof Fuse ? $target->breaker() : $target;
    }
}
