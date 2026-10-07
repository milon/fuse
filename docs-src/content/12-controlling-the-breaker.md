---
title: Controlling the breaker
---

# Controlling the breaker

`Fuse::run()` is the normal path. `breaker()` is how you look at the circuit and how you override it.

```php
$fuse = Fuse::for('billing-sdk', $store);

$breaker = $fuse->breaker();
```

## Read the circuit

| Method | Returns |
| --- | --- |
| `name()` | The name you passed to `Fuse::for()`, such as `billing-sdk` |
| `storageKey()` | The full key, such as `fuse:punt:billing:charge` |
| `operation()` / `app()` | The optional key segments |
| `state()` | `CircuitState::Closed`, `Open`, or `HalfOpen` |
| `secondsUntilRetry()` | Seconds until an open circuit may probe, `0` when the cooldown has elapsed, `null` when closed |
| `openException()` | A `CircuitOpenException` with name, key, and retry-after filled in |
| `allowRequest()` | Whether the next call may run. Also moves an open circuit to half-open when the cooldown is over, and reserves a probe slot |

`state()` reads the snapshot. It does not change it. `allowRequest()` can change it: when the open duration has elapsed, the call that checks is the one that becomes the probe.

```php
use Milon\Fuse\CircuitState;

if ($fuse->breaker()->state() === CircuitState::Open) {
    return $this->fallback();
}
```

Prefer catching `CircuitOpenException` from `run()`. Checking `state()` and then calling `run()` races with other processes, and `run()` already does the check.

```php
use Milon\Fuse\CircuitOpenException;

try {
    $fuse->run(fn () => $client->charge($amount));
} catch (CircuitOpenException $exception) {
    logger()->warning('circuit open', [
        'key' => $exception->storageKey,
        'retry_after' => $exception->retryAfterSeconds,
    ]);
}
```

In tests, prefer [Testing](13-testing.html) helpers (`assertCircuitOpen`, `forceCircuitOpen`) over reading `state()` by hand.

## Open it for a dependency you know is down

```php
$fuse->breaker()->forceOpen();
```

This writes an open snapshot with `opened_at` set to now and clears probe counters. Calls are rejected until `open_seconds` have passed. The next call after that is a half-open probe, the same as a circuit that opened because of failures.

One `forceOpen()` does not pin the circuit open forever. To cover a maintenance window longer than `open_seconds`, call `forceOpen()` again before the cooldown ends, or set `open_seconds` high on that breaker.

## Close it, or wipe it

```php
$fuse->breaker()->forceClosed();
```

The circuit is closed and the failure list is empty. The next call runs.

```php
$fuse->breaker()->reset();
```

This deletes the snapshot from the store. The next read is the same as a circuit that was never written: closed, with no failures. `forceClosed()` leaves a closed snapshot in the store. `reset()` leaves nothing.

## Record an outcome yourself

You only need these if you are not using `run()` or the Saloon trait.

| Method | Effect |
| --- | --- |
| `recordSuccess()` | While closed, clears failures. While half-open, counts a successful probe and closes the circuit when enough have passed. |
| `recordFailure()` | Adds a counted failure. Opens the circuit at the threshold, or immediately if it was half-open. |
| `releaseProbe()` | While half-open, frees one in-flight probe slot and does not change the success count. While closed or open, does nothing. |

`run()` calls these for you. The Saloon middleware calls them for you. Calling them as well as `run()` would count the same attempt twice.

## A clock you control

Tests that need the window or the cooldown to pass should not `sleep()`. Pass a clock:

```php
use Milon\Fuse\Clock;
use Milon\Fuse\Fuse;

$clock = new class implements Clock {
    public int $now = 1_700_000_000;

    public function now(): int
    {
        return $this->now;
    }
};

$fuse = Fuse::for('billing-sdk', $store, clock: $clock);

$clock->now += 60;
```

`now()` is a unix timestamp in seconds. The production default is `SystemClock`.
