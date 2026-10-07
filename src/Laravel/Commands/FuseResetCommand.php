<?php

declare(strict_types=1);

namespace Milon\Fuse\Laravel\Commands;

use Illuminate\Console\Command;
use Milon\Fuse\Laravel\FuseManager;

final class FuseResetCommand extends Command
{
    protected $signature = 'fuse:reset
                            {name : Circuit name}
                            {--operation= : Operation segment of the storage key}
                            {--app= : App segment of the storage key}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Reset a circuit breaker to closed with no recorded failures';

    public function __construct(private readonly FuseManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! is_string($name) || $name === '') {
            $this->components->error('A circuit name is required.');

            return self::FAILURE;
        }

        $operation = $this->optionalStringOption('operation');
        $app = $this->optionalStringOption('app');
        $breaker = $this->manager->for($name, operation: $operation, app: $app)->breaker();
        $previous = $breaker->state()->value;
        $key = $breaker->storageKey();

        if (! $this->option('force') && ! $this->confirm("Reset circuit [{$key}] (currently {$previous})?")) {
            $this->components->warn('Reset cancelled.');

            return self::SUCCESS;
        }

        $breaker->reset();

        $this->components->info("Reset circuit [{$key}]. Previous state: {$previous}.");

        return self::SUCCESS;
    }

    private function optionalStringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
