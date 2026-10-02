<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Stores\ArrayStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FuseManagerTest extends TestCase
{
    #[Test]
    public function named_breakers_override_the_shared_defaults(): void
    {
        $manager = new FuseManager(new ArrayStore, [
            'failure_threshold' => 4,
            'open_seconds' => 30,
            'breakers' => [
                'billing' => [
                    'failure_threshold' => 2,
                ],
            ],
        ]);

        $this->assertSame(4, $manager->configFor()->failureThreshold);
        $this->assertSame(30, $manager->configFor()->openSeconds);
        $this->assertSame(2, $manager->configFor('billing')->failureThreshold);
        $this->assertSame(30, $manager->configFor('billing')->openSeconds);
        $this->assertSame(4, $manager->configFor('other')->failureThreshold);
    }

    #[Test]
    public function for_builds_a_fuse_with_the_named_config_and_shared_store(): void
    {
        $store = new ArrayStore;
        $manager = new FuseManager($store, [
            'failure_threshold' => 3,
            'key_prefix' => 'app',
        ]);

        $fuse = $manager->for('billing', operation: 'charge', app: 'punt');

        $this->assertSame($store, $manager->store());
        $this->assertSame('billing', $fuse->breaker()->name());
        $this->assertSame('app:punt:billing:charge', $fuse->breaker()->storageKey());
        $this->assertSame(3, $manager->configFor('billing')->failureThreshold);
    }

    #[Test]
    public function an_explicit_config_replaces_the_named_one(): void
    {
        $manager = new FuseManager(new ArrayStore, [
            'failure_threshold' => 8,
        ]);

        $fuse = $manager->for('billing', config: new CircuitBreakerConfig(failureThreshold: 1));

        $fuse->breaker()->recordFailure();

        $this->assertSame(CircuitState::Open, $fuse->breaker()->state());
    }

    #[Test]
    public function a_non_array_breaker_entry_falls_back_to_the_defaults(): void
    {
        $manager = new FuseManager(new ArrayStore, [
            'failure_threshold' => 6,
            'breakers' => [
                'billing' => 'nope',
            ],
        ]);

        $this->assertSame(6, $manager->configFor('billing')->failureThreshold);
    }
}
