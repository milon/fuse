---
title: Database
---

# Database

The database store keeps one row per circuit in a table you own. Use it when you do not want a cache for this, or when you want the rows visible in SQL. The cache store is still the default.

## Publish and migrate

```shell
php artisan vendor:publish --tag=fuse-migrations
php artisan migrate
```

The published migration is `database/migrations/2026_10_02_000000_create_fuse_circuits_table.php`. It creates the table named by `config('fuse.database.table')`, which defaults to `fuse_circuits`.

Change the table name **before** you migrate. The migration reads the config at migrate time:

```php
'database' => [
    'connection' => env('FUSE_DB_CONNECTION'),
    'table' => 'circuit_breakers',
],
```

Then:

```dotenv
FUSE_STORE=database
FUSE_DB_CONNECTION=mysql
```

`FUSE_DB_CONNECTION` empty means the application's default connection.

The provider does not load the migration itself. If it did, publishing the file and also running the package copy would try to create the table twice. After you publish, `php artisan migrate` is the only step.

## Table

| Column | Type | |
| --- | --- | --- |
| `key` | string, 191, primary | Storage key, such as `fuse:billing-sdk` |
| `payload` | json | The snapshot |
| `expires_at` | unsigned big integer, nullable | Unix time. Null means no expiry |

`get()` deletes a row whose `expires_at` is in the past. You do not need a sweeper for correctness. A scheduled delete of expired rows is optional housekeeping.

## Using it

With `FUSE_STORE=database`, application code is the same as the cache store:

```php
use Milon\Fuse\CircuitOpenException;
use Milon\Fuse\Laravel\FuseManager;

try {
    app(FuseManager::class)
        ->for('billing-sdk')
        ->run(fn () => $this->billing->charge($amount));
} catch (CircuitOpenException) {
    return $this->fallback();
}
```

Two requests, two processes, one table: the second request sees the open circuit the first request recorded.

## Without the provider

Any `Illuminate\Database\ConnectionInterface` works:

```php
use Illuminate\Support\Facades\DB;
use Milon\Fuse\Fuse;
use Milon\Fuse\Stores\DatabaseStore;

$store = new DatabaseStore(DB::connection(), 'fuse_circuits');

Fuse::for('billing-sdk', $store)->run(fn () => $this->billing->charge($amount));
```

The table must already exist. The provider is what checks that the container's `db` binding is a Laravel connection resolver. If you select the database driver without `illuminate/database`, resolving the store throws `Fuse requires a Laravel database manager to use the database store.`

## Payload

`put()` JSON-encodes the snapshot and refuses to store a value that cannot be encoded. A row looks like:

```json
{
  "state": "open",
  "opened_at": 1710000000,
  "failures": [],
  "half_open_successes": 0,
  "half_open_inflight": 0
}
```

`state` is `closed`, `open`, or `half_open`.
