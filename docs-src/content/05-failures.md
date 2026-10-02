---
title: Failures
---

# Failures

Only a **counted failure** moves the circuit. Everything else is thrown again and the snapshot stays as it was, except that a half-open probe slot is released.

There are two paths:

- The callable **throws**. Fuse asks the failure classifier, or your `isFailure` callback.
- The callable **returns**. That is a success, unless `isSuccess` returns false, or a Saloon response has a counted status.

## Exceptions that count

With the defaults, `count_timeouts` and `count_connection_errors` are on.

A timeout matches when the exception class name contains `timeout` (any case), or the message contains `timed out`, `timeout`, or `operation timed out`.

A connection error matches when the class name contains `connect`, or the message contains one of:

- `connection refused`
- `could not resolve host`
- `failed to connect`
- `network is unreachable`
- `ssl`
- `tls`

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for('billing-sdk', new ArrayStore);

try {
    $fuse->run(fn () => throw new RuntimeException('cURL error 28: Operation timed out'));
} catch (RuntimeException) {
    // Counted. One step toward the threshold.
}
```

```php
try {
    $fuse->run(fn () => throw new RuntimeException('connection refused'));
} catch (RuntimeException) {
    // Counted.
}
```

Turn either kind off:

```php
new CircuitBreakerConfig(
    countTimeouts: false,
    countConnectionErrors: false,
);
```

## Exceptions that do not count

A business error, a validation error, or a 404 that you throw yourself is ignored:

```php
try {
    $fuse->run(fn () => throw new RuntimeException('card declined'));
} catch (RuntimeException) {
    // Rethrown. The circuit is still closed, and this did not add a failure.
}
```

That is deliberate. Opening the circuit because a customer typed a bad card number would block every later customer.

## HTTP statuses

Statuses are checked on Saloon responses, and anywhere you do the same check yourself. The default list is 502, 503, and 504. **500 does not count** until `count_http_500` is true.

```php
new CircuitBreakerConfig(
    countedHttpStatuses: [502, 503, 504, 429],
    countHttp500: true, // adds 500 to the list
);
```

`countHttp500` appends 500. It does not replace the list.

On a Saloon connector the middleware records a failure when `FailureClassifier::fromHttpStatus()` returns true for the response status. The request still returns the response. It does not throw just because the status was counted.

For a plain client that returns a status instead of throwing, use `isSuccess`:

```php
$fuse->run(
    execute: fn () => $client->send($request),
    isSuccess: function ($response): bool {
        return ! in_array($response->status(), [502, 503, 504], true);
    },
);
```

## Your own rule

`isFailure` replaces the classifier for that call:

```php
$fuse->run(
    execute: fn () => $client->charge($amount),
    isFailure: fn (Throwable $exception): bool => $exception instanceof UpstreamUnavailable,
);
```

`UpstreamUnavailable` counts. A timeout that is a different class does not, because the classifier is not consulted.

## What a counted failure does

| State when it happens | Result |
| --- | --- |
| Closed, count still under the threshold | Timestamp is stored. Circuit stays closed. |
| Closed, count reaches the threshold | Circuit opens. `opened_at` is now. |
| Half-open | Circuit opens immediately. Probe counters reset. |
| Open | The callable never ran, so nothing new is recorded. |

A success while closed deletes the stored timestamps. A success while half-open increments the probe success count, and closes the circuit when that count reaches `half_open_probes`.
