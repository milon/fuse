<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\DatabaseStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseStoreTest extends TestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required.');
        }

        $capsule = new Manager;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->bootEloquent();

        $this->connection = $capsule->getConnection();
        $this->connection->getSchemaBuilder()->create('fuse_circuits', function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->json('payload');
            $table->unsignedBigInteger('expires_at')->nullable();
        });
    }

    #[Test]
    public function a_new_store_instance_reads_state_written_by_another(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 1);
        $first = Fuse::for('billing', new DatabaseStore($this->connection), $config);

        try {
            $first->run(function (): never {
                throw new RuntimeException('connection refused');
            });
        } catch (RuntimeException) {
        }

        $second = Fuse::for('billing', new DatabaseStore($this->connection), $config);

        $this->assertSame(CircuitState::Open, $second->breaker()->state());
        $this->expectException(CircuitOpenException::class);
        $second->run(fn (): string => 'should not run');
    }

    #[Test]
    public function expired_rows_are_forgotten(): void
    {
        $store = new DatabaseStore($this->connection);
        $store->put('fuse:billing', [
            'state' => 'open',
            'opened_at' => time(),
            'failures' => [time()],
            'half_open_successes' => 0,
            'half_open_inflight' => 0,
        ], 30);

        $this->connection->table('fuse_circuits')->where('key', 'fuse:billing')->update([
            'expires_at' => time() - 5,
        ]);

        $this->assertNull($store->get('fuse:billing'));
        $this->assertSame(0, $this->connection->table('fuse_circuits')->count());
    }

    #[Test]
    public function forget_removes_the_row(): void
    {
        $store = new DatabaseStore($this->connection);
        $store->put('fuse:billing', [
            'state' => 'closed',
            'opened_at' => null,
            'failures' => [],
            'half_open_successes' => 0,
            'half_open_inflight' => 0,
        ], 30);

        $store->forget('fuse:billing');

        $this->assertNull($store->get('fuse:billing'));
    }
}
