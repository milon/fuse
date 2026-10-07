---
title: Plain PHP
---

# Plain PHP

This is the API when you are not using Laravel. You build a `Fuse` (or a `FuseFactory`), then call `run()`.

`ArrayStore` is used in the short examples because it needs no extra packages. It forgets everything when the process ends. For a web request that must remember the last request, use a [PSR-16 store](06-stores.html) instead.

## A successful call

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for('billing-sdk', new ArrayStore);

$invoice = $fuse->run(function () {
    return $this->billing->charge($amount);
});
```

`run()` returns whatever the callable returns. A normal return is a success and clears any recorded failures.

## When the circuit is open

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for('billing-sdk', new ArrayStore);

try {
    $invoice = $fuse->run(fn () => $this->billing->charge($amount));
} catch (CircuitOpenException $exception) {
    // The callable did not run.
    $exception->circuitName;        // billing-sdk
    $exception->storageKey;         // fuse:billing-sdk
    $exception->retryAfterSeconds;  // e.g. 27
    $exception->operation;          // null, or "charge"
    $exception->app;                // null, or "punt"

    return $this->fallback();
}
```

A typical message looks like:

`Circuit [billing-sdk] is open; key [fuse:billing-sdk]; retry after approximately 27s`

When the cooldown has already elapsed but no probe slot is free, `retryAfterSeconds` is `0` and the message says `cooldown elapsed`.

## A threshold that opens on the next failure

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$store = new ArrayStore;

$fuse = Fuse::for(
    name: 'billing-sdk',
    store: $store,
    config: new CircuitBreakerConfig(failureThreshold: 1, openSeconds: 30),
);

try {
    $fuse->run(function () {
        throw new RuntimeException('connection refused');
    });
} catch (RuntimeException) {
    // Counted. The circuit is now open.
}

try {
    $fuse->run(fn () => 'this callable is not invoked');
} catch (CircuitOpenException $exception) {
    echo $exception->circuitName; // billing-sdk
}
```

Build the second `Fuse` from the **same store** if you want it to see the open circuit. A new `ArrayStore` is empty.

## FuseFactory: shared store, named breakers, events

When several circuits share one store and you want per-name config overrides (the same idea as Laravel's `config/fuse.php`), use `FuseFactory`:

```php
use Milon\Fuse\Events\CallableCircuitEventDispatcher;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\FuseFactory;
use Milon\Fuse\Stores\Psr16Store;

$events = (new CallableCircuitEventDispatcher)
    ->listenFor(CircuitOpened::class, function (CircuitOpened $event): void {
        error_log("circuit open: {$event->storageKey} ({$event->reason})");
    });

$factory = new FuseFactory(
    store: new Psr16Store($cache),
    settings: [
        'failure_threshold' => 8,
        'open_seconds' => 30,
        'breakers' => [
            'billing' => [
                'failure_threshold' => 3,
                'open_seconds' => 15,
            ],
        ],
    ],
    events: $events,
);

$factory->for('billing')->run(fn () => $billing->charge($amount));
$factory->for('search')->run(fn () => $search->query($q)); // still uses threshold 8
```

Useful methods:

| Method | Returns |
| --- | --- |
| `for($name, operation:, app:, config:)` | A `Fuse` bound to the shared store and merged config |
| `configFor($name)` | Merged `CircuitBreakerConfig` for that name |
| `store()` | The shared store |
| `events()` | The dispatcher, or `null` |
| `breakerNames()` | Keys under `settings['breakers']` |

Laravel's `FuseManager` extends this class. Saloon connectors resolve a container-bound `FuseFactory` or `FuseManager` for store, config, and events when one is present.

## Listening for state changes

Circuits dispatch plain event objects when the state changes:

| Event | When |
| --- | --- |
| `CircuitOpened` | Closed or half-open → open |
| `CircuitHalfOpened` | Open → half-open |
| `CircuitClosed` | Open or half-open → closed (including `reset()`) |

Each event has `name`, `storageKey`, `operation`, `app`, `previous` (`CircuitState`), and `reason` (`failure_threshold`, `probe_failed`, `probes_succeeded`, `cooldown_elapsed`, `forced`, `reset`, …).

### Callable dispatcher

```php
use Milon\Fuse\Events\CallableCircuitEventDispatcher;
use Milon\Fuse\Events\CircuitClosed;
use Milon\Fuse\Events\CircuitOpened;
use Milon\Fuse\Fuse;

$events = (new CallableCircuitEventDispatcher)
    ->listenFor(CircuitOpened::class, function (CircuitOpened $event): void {
        // alert, metric, log…
    })
    ->listen(function (object $event): void {
        // every circuit event
    });

$fuse = Fuse::for(
    name: 'billing',
    store: $store,
    events: $events,
);
```

`listenFor()` only runs for that class. `listen()` runs for every dispatched object. You can also pass listeners into the constructor: `new CallableCircuitEventDispatcher($listener)`.

### PSR-14 dispatcher

```php
use Milon\Fuse\Events\Psr14CircuitEventDispatcher;
use Milon\Fuse\FuseFactory;

$factory = new FuseFactory(
    store: $store,
    settings: [],
    events: new Psr14CircuitEventDispatcher($psr14Dispatcher),
);
```

Requires `psr/event-dispatcher`. Fuse calls `$psr14Dispatcher->dispatch($event)` on each transition.

## Separate operations

`charge` and `refund` are different circuits when you pass an operation:

```php
$charge = Fuse::for('billing', $store, operation: 'charge');
$refund = Fuse::for('billing', $store, operation: 'refund');
```

Their keys are `fuse:billing:charge` and `fuse:billing:refund`. Opening one does not open the other.

Pass `app` when several applications share a store:

```php
$fuse = Fuse::for('billing', $store, app: 'punt', operation: 'charge');
// key: fuse:punt:billing:charge
```

## Decide yourself what counts

By default a thrown exception is classified for you. Pass `isFailure` to replace that:

```php
$fuse->run(
    execute: fn () => $client->get('/health'),
    isFailure: function (Throwable $exception): bool {
        return $exception instanceof BillingUnavailable;
    },
);
```

Return `true` to record a failure. Return `false` to rethrow and leave the circuit alone. Timeouts no longer count automatically once you pass this callback.

A returned value is a success unless you pass `isSuccess`:

```php
$fuse->run(
    execute: fn () => $client->get('/charge'),
    isSuccess: fn (Response $response): bool => $response->status() < 500,
);
```

`false` records a failure and still returns the value. The callable is not thrown away. Use this when the client returns an error response instead of throwing.

Both callbacks can be set together. `isFailure` only runs when the callable throws. `isSuccess` only runs when it returns.

## Sharing one fuse

Create the `Fuse` once and reuse it for every call to that dependency. The store holds the state, so two `Fuse` objects with the same name and the same store share one circuit:

```php
$store = new ArrayStore; // or a shared PSR-16 cache

$first = Fuse::for('billing-sdk', $store);
$second = Fuse::for('billing-sdk', $store);

$first->breaker()->forceOpen();

$second->breaker()->state(); // CircuitState::Open
```

Or keep one `FuseFactory` and call `for()` per dependency name.
