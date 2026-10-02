---
title: Plain PHP
---

# Plain PHP

This is the whole API when you are not using Laravel or Saloon. You build a `Fuse`, then call `run()`.

`ArrayStore` is used below because it needs no extra packages. It forgets everything when the process ends. For a web request that must remember the last request, use a [PSR-16 store](06-stores.html) instead. The calls below do not change.

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
    // The callable did not run. $exception->circuitName is "billing-sdk".
    return $this->fallback();
}
```

`CircuitOpenException` extends `RuntimeException`. The message is `Circuit [billing-sdk] is open`.

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
