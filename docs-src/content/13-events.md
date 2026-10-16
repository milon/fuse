---
title: Events
---

# Events

When a circuit **changes state**, Fuse dispatches a plain PHP event object. Nothing is dispatched for a failure that stays closed, a success that stays closed, or a probe reservation that stays half-open — only real transitions.

Events are optional. Pass a `CircuitEventDispatcher` into `Fuse::for(..., events: …)` or `FuseFactory`, or use Laravel's built-in dispatcher when the service provider is registered.

## The three events

| Event | Transition |
| --- | --- |
| `Milon\Fuse\Events\CircuitOpened` | Closed or half-open → **open** |
| `Milon\Fuse\Events\CircuitHalfOpened` | Open → **half-open** |
| `Milon\Fuse\Events\CircuitClosed` | Open or half-open → **closed** (including `reset()`) |

Every event has the same properties:

| Property | Type | Meaning |
| --- | --- | --- |
| `name` | `string` | Circuit name (`billing`) |
| `storageKey` | `string` | Full key (`fuse:punt:billing:charge`) |
| `operation` | `?string` | Operation segment, if any |
| `app` | `?string` | App segment, if any |
| `previous` | `CircuitState` | State before this transition |
| `reason` | `string` | Why the transition happened |

## Reasons

`reason` is a short snake_case string. The values you see on events are the ones that caused a state change:

| Reason | Typical transition |
| --- | --- |
| `failure_threshold` | Closed → open (enough counted failures) |
| `probe_failed` | Half-open → open |
| `probes_succeeded` | Half-open → closed |
| `cooldown_elapsed` | Open → half-open |
| `forced` | `forceOpen()` / `forceClosed()` |
| `reset` | `reset()` → closed |

Other internal persist labels exist (`success`, `failure_recorded`, `probe_released`, …) but they do not emit events when the state stays the same.

## Plain PHP: callable dispatcher

No framework required. Listen for one class or for every event:

```php
use Milon\Fuse\Events\CallableCircuitEventDispatcher;
use Milon\Fuse\Events\CircuitClosed;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$events = (new CallableCircuitEventDispatcher)
    ->listenFor(CircuitOpened::class, function (CircuitOpened $event): void {
        error_log("OPEN {$event->storageKey} reason={$event->reason}");
    })
    ->listenFor(CircuitClosed::class, function (CircuitClosed $event): void {
        error_log("CLOSED {$event->storageKey}");
    })
    ->listen(function (object $event): void {
        // every circuit event
    });

$fuse = Fuse::for(
    name: 'billing',
    store: new ArrayStore,
    events: $events,
);
```

`listenFor()` filters by class. `listen()` runs for every dispatched object. You can also pass listeners into the constructor: `new CallableCircuitEventDispatcher($listener)`.

Attach the same dispatcher to a factory so every `for()` shares it:

```php
use Milon\Fuse\FuseFactory;

$factory = new FuseFactory(
    store: $store,
    settings: [
        'breakers' => [
            'billing' => ['failure_threshold' => 3],
        ],
    ],
    events: $events,
);

$factory->for('billing')->run(fn () => $billing->charge($amount));
```

## Plain PHP: PSR-14

Bridge into any PSR-14 dispatcher (requires `psr/event-dispatcher`):

```php
use Milon\Fuse\Events\Psr14CircuitEventDispatcher;
use Milon\Fuse\FuseFactory;

$factory = new FuseFactory(
    store: $store,
    settings: [],
    events: new Psr14CircuitEventDispatcher($psr14Dispatcher),
);
```

Fuse calls `$psr14Dispatcher->dispatch($event)` on each transition. Register listeners on your PSR-14 bus as usual.

## Laravel

The service provider binds `CircuitEventDispatcher` to `LaravelCircuitEventDispatcher`, which forwards into Laravel's event system. Listen with the facade, attributes, or `EventServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use Milon\Fuse\Events\CircuitHalfOpened;
use Milon\Fuse\Events\CircuitOpened;

Event::listen(CircuitOpened::class, function (CircuitOpened $event): void {
    logger()->warning('Circuit opened', [
        'name' => $event->name,
        'key' => $event->storageKey,
        'previous' => $event->previous->value,
        'reason' => $event->reason,
    ]);
});

Event::listen(CircuitHalfOpened::class, function (CircuitHalfOpened $event): void {
    logger()->info('Circuit half-open', ['key' => $event->storageKey]);
});
```

Or a dedicated listener class:

```php
namespace App\Listeners;

use Milon\Fuse\Events\CircuitOpened;

final class ReportCircuitOpened
{
    public function handle(CircuitOpened $event): void
    {
        // Slack, metrics, …
    }
}
```

```php
// EventServiceProvider or bootstrap
Event::listen(CircuitOpened::class, ReportCircuitOpened::class);
```

`Fuse::for(...)`, `fuse(...)`, and Saloon connectors that use `HasCircuitBreaker` all share this dispatcher. You do not pass `events:` yourself in a normal Laravel app.

## Saloon

If the connector resolves a `FuseFactory` / `FuseManager` (Laravel by default, or your own binding), circuit transitions from `send()` use that factory's dispatcher.

Outside Laravel, inject the factory (or override `resolveCircuitEventDispatcher()`):

```php
use Milon\Fuse\Contracts\CircuitEventDispatcher;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function __construct(private FuseFactory $fuse) {}

    protected function resolveCircuitEventDispatcher(): ?CircuitEventDispatcher
    {
        return $this->fuse->events();
    }

    // store + config from $this->fuse as in the Saloon chapter
}
```

## Events vs CircuitOpenException

| | Events | `CircuitOpenException` |
| --- | --- | --- |
| When | State changes (any process that trips or recovers the circuit) | A call is **rejected** because the circuit is already open (or half-open with no probe slot) |
| Use for | Logging, alerts, metrics | Call-site fallbacks, HTTP `Retry-After` |

Opening the circuit (threshold hit) fires `CircuitOpened`. Later rejected calls throw `CircuitOpenException` and do **not** fire another open event.

## Custom dispatcher

Implement `Milon\Fuse\Contracts\CircuitEventDispatcher` if you need something else:

```php
use Milon\Fuse\Contracts\CircuitEventDispatcher;

final class LoggingDispatcher implements CircuitEventDispatcher
{
    public function dispatch(object $event): void
    {
        // forward to your bus
    }
}
```

Pass it as `events:` to `Fuse::for()` or `FuseFactory`.
