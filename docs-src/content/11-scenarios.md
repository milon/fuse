---
title: Scenarios
---

# Scenarios

Each block below is a complete path for one situation: what is installed, what you write, and what Fuse does. The earlier chapters explain the pieces. This page is the set you can copy.

## 1. One call, plain PHP, in-memory

No Laravel, no Saloon. State lasts for this process only.

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for('billing-sdk', new ArrayStore);

try {
    $body = $fuse->run(function () {
        $ch = curl_init('https://billing.example.com/charge');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $body = curl_exec($ch);

        if ($body === false) {
            throw new RuntimeException(curl_error($ch));
        }

        return $body;
    });
} catch (CircuitOpenException) {
    $body = null;
}
```

A cURL message of `Connection timed out` or `connection refused` is a counted failure. A normal body is a success.

## 2. The circuit opens, then rejects

Threshold 2, so the second counted failure opens it and the third call never runs.

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$store = new ArrayStore;

$fuse = Fuse::for('billing-sdk', $store, new CircuitBreakerConfig(
    failureThreshold: 2,
    openSeconds: 30,
));

$call = function () use ($fuse) {
    return $fuse->run(fn () => throw new RuntimeException('connection refused'));
};

try { $call(); } catch (RuntimeException) {}
try { $call(); } catch (RuntimeException) {}

$fuse->breaker()->state(); // CircuitState::Open

try {
    $fuse->run(fn () => 'not called');
} catch (CircuitOpenException $e) {
    $e->circuitName; // billing-sdk
}
```

## 3. Failures age out of the window

Three failures are required, and the window is 10 seconds. The first failure is older than the window by the time the third happens, so the circuit stays closed.

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$clock = new class implements Milon\Fuse\Clock {
    public int $now = 1_000;

    public function now(): int
    {
        return $this->now;
    }
};

$fuse = Fuse::for(
    'billing-sdk',
    new ArrayStore,
    new CircuitBreakerConfig(failureThreshold: 3, failureWindowSeconds: 10),
    clock: $clock,
);

$fail = fn () => $fuse->run(fn () => throw new RuntimeException('timed out'));

try { $fail(); } catch (RuntimeException) {}
$clock->now += 11;
try { $fail(); } catch (RuntimeException) {}
try { $fail(); } catch (RuntimeException) {}

$fuse->breaker()->state(); // still closed: only two timestamps are inside the window
```

Pass any `Milon\Fuse\Clock`. Production uses `SystemClock`, which returns `time()`.

## 4. Half-open recovery

Open for 1 second, one probe. After the cooldown the next call runs. Success closes the circuit.

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$store = new ArrayStore;

$fuse = Fuse::for('billing-sdk', $store, new CircuitBreakerConfig(
    failureThreshold: 1,
    openSeconds: 1,
    halfOpenProbes: 1,
));

try {
    $fuse->run(fn () => throw new RuntimeException('connection refused'));
} catch (RuntimeException) {
}

sleep(1);

$fuse->run(fn () => 'recovered');

$fuse->breaker()->state(); // CircuitState::Closed
```

If that probe had thrown `connection refused` again, the state would be `CircuitState::Open` and the cooldown would start over.

With `halfOpenProbes: 2`, the first success leaves the circuit half-open. The second success closes it. A counted failure on either probe opens it.

## 5. A business error does not open it

```php
use Milon\Fuse\CircuitBreakerConfig;
use Milon\Fuse\CircuitState;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for('billing-sdk', new ArrayStore, new CircuitBreakerConfig(
    failureThreshold: 1,
));

try {
    $fuse->run(fn () => throw new RuntimeException('card declined'));
} catch (RuntimeException) {
}

$fuse->breaker()->state(); // CircuitState::Closed
```

## 6. You decide, with isFailure and isSuccess

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\ArrayStore;

$fuse = Fuse::for('billing-sdk', new ArrayStore);

$result = $fuse->run(
    execute: fn () => $client->charge($amount), // returns ['ok' => false] or throws
    isFailure: fn (Throwable $e): bool => $e instanceof UpstreamUnavailable,
    isSuccess: fn (array $body): bool => ($body['ok'] ?? false) === true,
);
```

`UpstreamUnavailable` counts. Any other throw does not. A body with `ok: false` counts as a failure and is still returned in `$result`.

## 7. PSR-16 in a long-running worker

```php
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\Psr16Store;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new RedisAdapter(
    RedisAdapter::createConnection('redis://localhost'),
));

$store = new Psr16Store($cache);

Fuse::for('billing-sdk', $store)->run(fn () => $client->charge($amount));
```

The next process that constructs `Psr16Store` with that Redis sees the same snapshot. Symfony is only an example of `CacheInterface`. Any PSR-16 cache is the same two lines: wrap it, pass it to `Fuse::for()`.

## 8. Laravel, cache, named breaker

`.env`:

```dotenv
FUSE_STORE=cache
FUSE_CACHE_STORE=redis
CACHE_STORE=redis
```

`config/fuse.php`:

```php
'breakers' => [
    'billing-sdk' => [
        'failure_threshold' => 3,
        'open_seconds' => 15,
    ],
],
```

Controller:

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Laravel\FuseManager;

public function store(FuseManager $fuse, BillingClient $billing)
{
    try {
        return $fuse->for('billing-sdk')->run(
            fn () => $billing->charge(request('amount')),
        );
    } catch (CircuitOpenException) {
        return response()->json(['message' => 'Try again shortly.'], 503);
    }
}
```

## 9. Laravel, database

```shell
php artisan vendor:publish --tag=fuse-migrations
php artisan migrate
```

```dotenv
FUSE_STORE=database
```

```php
app(Milon\Fuse\Laravel\FuseManager::class)
    ->for('billing-sdk')
    ->run(fn () => $billing->charge($amount));
```

Inspect it:

```sql
select `key`, payload, expires_at from fuse_circuits;
```

## 10. Saloon connector, no Laravel

```php
use Milon\Fuse\Contracts\CircuitBreakerStore;
use Milon\Fuse\Saloon\Contracts\HasCircuitBreakerOperation;
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Milon\Fuse\Stores\Psr16Store;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Request;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function __construct(private Psr\SimpleCache\CacheInterface $cache) {}

    public function resolveBaseUrl(): string
    {
        return 'https://billing.example.com';
    }

    protected function resolveCircuitBreakerStore(): CircuitBreakerStore
    {
        return new Psr16Store($this->cache);
    }
}

class ChargeRequest extends Request implements HasCircuitBreakerOperation
{
    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/charge';
    }

    public function resolveCircuitBreakerOperation(): string
    {
        return 'charge';
    }
}

$response = (new BillingConnector($cache))->send(new ChargeRequest);
```

The default circuit name is derived from the class, so this one is `billing`. Eight responses with status 503 (the default threshold) open `fuse:billing:charge`. The ninth `send()` throws `CircuitOpenException` and does not leave the machine. A later 200 after the cooldown is a probe.

## 11. Laravel and Saloon together

`FUSE_STORE=cache` or `database`. Add the trait; the bound store and named config are resolved for you:

```php
use Milon\Fuse\Saloon\Traits\HasCircuitBreaker;
use Saloon\Http\Connector;

class BillingConnector extends Connector
{
    use HasCircuitBreaker;

    public function resolveBaseUrl(): string
    {
        return 'https://billing.example.com';
    }
}
```

A 503 from `ChargeRequest` (operation `charge`) and a later `FuseManager::for('billing', operation: 'charge')` see one circuit.

## 12. Trip one operation, leave the other alone

```php
$store = new ArrayStore; // or the shared Laravel store

$charge = Fuse::for('billing', $store, new CircuitBreakerConfig(failureThreshold: 1), 'charge');
$refund = Fuse::for('billing', $store, operation: 'refund');

try {
    $charge->run(fn () => throw new RuntimeException('connection refused'));
} catch (RuntimeException) {
}

$refund->run(fn () => 'refunds still run');
```

## 13. Maintenance window

Open the circuit yourself, then close it when the dependency is back. Details are in [Controlling the breaker](12-controlling-the-breaker.html).

```php
$fuse = Fuse::for('billing-sdk', $store);

$fuse->breaker()->forceOpen();

// ... dependency is back ...

$fuse->breaker()->forceClosed();
```

`forceOpen()` writes an open snapshot with `opened_at` set to now. Calls are rejected until `open_seconds` have passed, then the next call is a probe, the same as a circuit that opened from failures. Call `forceOpen()` again before the cooldown ends to keep it open longer. `forceClosed()` or `reset()` lets traffic through immediately.
