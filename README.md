# [SubstancePHP](https://github.com/substancephp): [SQL](https://packagist.org/packages/substancephp/sql)

## Overview

`substancephp/sql` is a SQL query builder library for PHP, and a migration runner.

## Installation

```
composer require substancephp/sql
```

Note PHP 8.4 or greater is required.

## Usage

### Query builder

```php
use SubstancePHP\SQL\Query;

$pdo = new PDO('sqlite::memory:');

Query::insertInto('things', ['x' => 3, 'y' => 'cool'])->run($pdo);
$rows = Query::select(['x', 'y'])->from('things')->fetchAll($pdo);
```

TODO: More

### Transactions

`Transaction::run` runs a callback inside a transaction, which is committed if the callback returns, and
rolled back if it throws. It returns whatever the callback returns.

```php
use SubstancePHP\SQL\Transaction;

$id = Transaction::run($pdo, function (PDO $pdo): int {
    Query::insertInto('users', ['email' => 'someone@example.com'])->run($pdo);
    return (int) $pdo->lastInsertId();
});
```

Nesting is handled automatically. The outermost scope begins and commits a real transaction, while scopes
nested inside it are delimited by savepoints, so an inner scope can fail and be rolled back on its own,
without discarding the work of the scope enclosing it.

```php
Transaction::run($pdo, function (PDO $pdo): void {
    Query::insertInto('users', ['email' => 'someone@example.com'])->run($pdo);
    try {
        Transaction::run($pdo, fn (PDO $pdo) => somethingRisky($pdo));
    } catch (Throwable) {
        // Only the inner scope was rolled back. The enclosing transaction is still usable, and the row
        // inserted above will still be committed when it finishes.
    }
});
```

If a transaction is already in progress on the connection when the outermost scope begins, for example
because `PDO::beginTransaction()` was called directly, then it is adopted rather than replaced. The scope
nests inside it via a savepoint, and is left for its owner to commit or roll back.

Nesting is implemented using savepoints, so it requires a database that supports them.

### Migrations

`MigrationRunner` applies and reverts migrations found as PHP files in a directory. Migration files are sorted by
file name (natural sort). Each file must return a `SubstancePHP\SQL\Migration` instance.

```php
use SubstancePHP\SQL\MigrationRunner;

$pdo = new PDO('pgsql:host=localhost;dbname=app', 'user', 'password');
$runner = new MigrationRunner($pdo, __DIR__ . '/migrations');

$runner->migrate();     // apply all pending migrations
$runner->rollback();    // revert the last applied migration
$runner->migrate(2);    // apply at most the next 2 pending migrations
$runner->rollback(3);   // revert the last 3 applied migrations
$runner->status();      // list applied/pending migrations
```

Example callable migration file `002_add_posts.php`:

```php
<?php

declare(strict_types=1);

use SubstancePHP\SQL\Migration;

return new Migration(
    function (PDO $pdo): void {
        $pdo->exec('create table posts (id integer primary key, user_id integer)');
    },
    function (PDO $pdo): void {
        $pdo->exec('drop table posts');
    },
);
```

Applied migrations are recorded in a `migrations` table (configurable via the runner's third constructor argument).
Each migration automatically runs inside a transaction.
