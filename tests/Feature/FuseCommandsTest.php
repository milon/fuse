<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Container\Container;
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Laravel\Commands\FuseResetCommand;
use Milon\Fuse\Laravel\Commands\FuseStatusCommand;
use Milon\Fuse\Laravel\FuseManager;
use Milon\Fuse\Stores\ArrayStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FuseCommandsTest extends TestCase
{
    #[Test]
    public function status_lists_configured_breakers(): void
    {
        $store = new ArrayStore;
        $manager = new FuseManager($store, [
            'breakers' => [
                'billing' => ['failure_threshold' => 1],
                'search' => [],
            ],
        ]);

        $manager->for('billing', config: new CircuitBreakerConfig(failureThreshold: 1))->breaker()->forceOpen();

        $tester = $this->tester(new FuseStatusCommand($manager));
        $tester->execute([]);

        $display = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('billing', $display);
        $this->assertStringContainsString('search', $display);
        $this->assertStringContainsString('open', $display);
        $this->assertStringContainsString('closed', $display);
    }

    #[Test]
    public function status_shows_a_single_circuit(): void
    {
        $manager = new FuseManager(new ArrayStore, [
            'breakers' => [
                'billing' => ['failure_threshold' => 1],
            ],
        ]);

        $manager->for('billing', operation: 'charge')->breaker()->forceOpen();

        $tester = $this->tester(new FuseStatusCommand($manager));
        $tester->execute([
            'name' => 'billing',
            '--operation' => 'charge',
        ]);

        $display = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('fuse:billing:charge', $display);
        $this->assertStringContainsString('open', $display);
        $this->assertSame(CircuitState::Open, $manager->for('billing', operation: 'charge')->breaker()->state());
    }

    #[Test]
    public function reset_clears_an_open_circuit(): void
    {
        $manager = new FuseManager(new ArrayStore, []);
        $manager->for('billing')->breaker()->forceOpen();

        $tester = $this->tester(new FuseResetCommand($manager));
        $tester->execute([
            'name' => 'billing',
            '--force' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('fuse:billing', $tester->getDisplay());
        $this->assertSame(CircuitState::Closed, $manager->for('billing')->breaker()->state());
    }

    private function tester(Command $command): CommandTester
    {
        $container = new class extends Container
        {
            public function runningUnitTests(): bool
            {
                return true;
            }
        };

        $container->bind(OutputStyle::class, fn ($app, array $params): OutputStyle => new OutputStyle($params['input'], $params['output']));
        $container->bind(Factory::class, fn ($app, array $params): Factory => new Factory($params['output']));

        $command->setLaravel($container);

        return new CommandTester($command);
    }
}
