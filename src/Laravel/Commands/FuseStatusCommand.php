<?php

declare(strict_types=1);

namespace Milon\Fuse\Laravel\Commands;

use Illuminate\Console\Command;
use Milon\Fuse\CircuitSnapshot;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Laravel\FuseManager;

final class FuseStatusCommand extends Command
{
    protected $signature = 'fuse:status
                            {name? : Circuit name (omit to list configured breakers)}
                            {--operation= : Operation segment of the storage key}
                            {--app= : App segment of the storage key}';

    protected $description = 'Show circuit breaker status';

    public function __construct(private readonly FuseManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! is_string($name) || $name === '') {
            return $this->listConfiguredBreakers();
        }

        return $this->showOne(
            name: $name,
            operation: $this->optionalStringOption('operation'),
            app: $this->optionalStringOption('app'),
        );
    }

    private function listConfiguredBreakers(): int
    {
        $names = $this->manager->breakerNames();

        if ($names === []) {
            $this->components->warn('No named breakers in config/fuse.php. Pass a circuit name to inspect one.');

            return self::SUCCESS;
        }

        $operation = $this->optionalStringOption('operation');
        $app = $this->optionalStringOption('app');

        $rows = [];

        foreach ($names as $name) {
            $breaker = $this->manager->for($name, operation: $operation, app: $app)->breaker();
            $raw = $this->manager->store()->get($breaker->storageKey());
            $failures = $raw === null ? 0 : count(CircuitSnapshot::fromArray($raw)->failures);

            $rows[] = [
                $name,
                $breaker->state()->value,
                $breaker->storageKey(),
                (string) $failures,
            ];
        }

        $this->table(['Name', 'State', 'Key', 'Failures'], $rows);

        return self::SUCCESS;
    }

    private function showOne(string $name, ?string $operation, ?string $app): int
    {
        $breaker = $this->manager->for($name, operation: $operation, app: $app)->breaker();
        $raw = $this->manager->store()->get($breaker->storageKey());
        $state = $breaker->state();
        $snapshot = $raw === null ? null : CircuitSnapshot::fromArray($raw);

        $this->table(['Field', 'Value'], [
            ['Name', $name],
            ['Operation', $operation ?? '—'],
            ['App', $app ?? '—'],
            ['Key', $breaker->storageKey()],
            ['State', $state->value],
            ['Failures', (string) ($snapshot === null ? 0 : count($snapshot->failures))],
            ['Opened at', $snapshot !== null && $snapshot->openedAt !== null ? (string) $snapshot->openedAt : '—'],
            ['Half-open successes', (string) ($snapshot === null ? 0 : $snapshot->halfOpenSuccesses)],
            ['Half-open inflight', (string) ($snapshot === null ? 0 : $snapshot->halfOpenInflight)],
            ['Stored', $raw === null ? 'no (defaults to closed)' : 'yes'],
        ]);

        if ($state === CircuitState::Open) {
            $this->components->error("Circuit [{$name}] is open.");
        } elseif ($state === CircuitState::HalfOpen) {
            $this->components->warn("Circuit [{$name}] is half-open.");
        } else {
            $this->components->info("Circuit [{$name}] is closed.");
        }

        return self::SUCCESS;
    }

    private function optionalStringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
