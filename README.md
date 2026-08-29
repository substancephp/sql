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
