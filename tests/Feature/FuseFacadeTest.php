<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Cache\ArrayStore as CacheArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Milon\Fuse\Fuse as FuseInstance;
use Milon\Fuse\Laravel\Facades\Fuse;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Stores\LaravelCacheStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FuseFacadeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::setFacadeApplication($this->container);

        $manager = new FuseManager(new LaravelCacheStore(new CacheRepository(new CacheArrayStore)), [
            'failure_threshold' => 3,
            'breakers' => [
                'billing' => [
                    'failure_threshold' => 1,
                ],
            ],
        ]);

        $this->container->instance(FuseManager::class, $manager);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    #[Test]
    public function the_facade_proxies_to_the_manager(): void
    {
        $fuse = Fuse::for('billing');

        $this->assertInstanceOf(FuseInstance::class, $fuse);
        $this->assertSame(1, Fuse::configFor('billing')->failureThreshold);
        $this->assertSame(3, Fuse::configFor('search')->failureThreshold);
        $this->assertSame($this->container->make(FuseManager::class)->store(), Fuse::store());
    }

    #[Test]
    public function the_fuse_helper_resolves_the_manager_or_a_named_circuit(): void
    {
        $this->assertInstanceOf(FuseManager::class, fuse());
        $this->assertInstanceOf(FuseInstance::class, fuse('billing', operation: 'charge'));
        $this->assertSame(1, fuse()->configFor('billing')->failureThreshold);
    }
}
