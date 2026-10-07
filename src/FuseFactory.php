<?php

declare(strict_types=1);

namespace Milon\Fuse;

use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Contracts\CircuitEventDispatcher;

/**
 * Shared store, defaults, named breaker overrides, and optional events.
 *
 * Framework-agnostic counterpart to the Laravel {@see \Milon\Fuse\Laravel\FuseManager}.
 */
class FuseFactory
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        private readonly CircuitBreakerStore $store,
        private readonly array $settings = [],
        private readonly ?CircuitEventDispatcher $events = null,
    ) {}

    public function store(): CircuitBreakerStore
    {
        return $this->store;
    }

    public function events(): ?CircuitEventDispatcher
    {
        return $this->events;
    }

    /**
     * @return list<string>
     */
    public function breakerNames(): array
    {
        $breakers = $this->settings['breakers'] ?? null;

        if (! is_array($breakers)) {
            return [];
        }

        $names = [];

        foreach ($breakers as $name => $overrides) {
            if (is_string($name) && $name !== '' && is_array($overrides)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function configFor(?string $name = null): CircuitBreakerConfig
    {
        return CircuitBreakerConfig::fromArray($this->settingsFor($name));
    }

    public function for(
        string $name,
        ?string $operation = null,
        ?string $app = null,
        ?CircuitBreakerConfig $config = null,
    ): Fuse {
        return Fuse::for(
            name: $name,
            store: $this->store,
            config: $config ?? $this->configFor($name),
            operation: $operation,
            app: $app,
            events: $this->events,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFor(?string $name): array
    {
        if ($name === null) {
            return $this->settings;
        }

        return [...$this->settings, ...$this->overridesFor($name)];
    }

    /**
     * @return array<string, mixed>
     */
    private function overridesFor(string $name): array
    {
        $breakers = $this->settings['breakers'] ?? null;

        if (! is_array($breakers) || ! isset($breakers[$name]) || ! is_array($breakers[$name])) {
            return [];
        }

        $overrides = [];

        foreach ($breakers[$name] as $key => $value) {
            if (is_string($key)) {
                $overrides[$key] = $value;
            }
        }

        return $overrides;
    }
}
