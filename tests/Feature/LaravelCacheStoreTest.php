<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Cache\ArrayStore as CacheArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Stores\LaravelCacheStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LaravelCacheStoreTest extends TestCase
{
    #[Test]
    public function the_manager_shares_circuit_state_through_the_laravel_cache(): void
    {
        $manager = new FuseManager(new LaravelCacheStore(new CacheRepository(new CacheArrayStore)), [
            'failure_threshold' => 1,
            'key_prefix' => 'laravel',
        ]);

        $fuse = $manager->for('billing');

        try {
            $fuse->run(function (): never {
                throw new RuntimeException('connection refused');
            });
        } catch (RuntimeException) {
        }

        $again = $manager->for('billing');

        $this->assertSame(CircuitState::Open, $again->breaker()->state());
        $this->expectException(CircuitOpenException::class);
        $again->run(fn (): string => 'should not run');
    }
}
