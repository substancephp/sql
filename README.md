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
file name (natural sort), and each file must return either:

- a `SubstancePHP\SQL\Migration` instance, or
- an array with `up` and `down` entries.

Each `up`/`down` entry may be a SQL string, a list of SQL strings (executed in order), or a callable receiving
the `PDO` instance.

```php
use SubstancePHP\SQL\MigrationRunner;

$pdo = new PDO('pgsql:host=localhost;dbname=app', 'user', 'password');
$runner = new MigrationRunner($pdo, __DIR__ . '/migrations');

$runner->up();          // apply all pending migrations
$runner->down();        // revert the last applied migration
$runner->up(2);         // apply at most the next 2 pending migrations
$runner->down(3);       // revert the last 3 applied migrations
$runner->status();      // list applied/pending migrations
```

Example migration file `001_create_users.php`:

```php
<?php

declare(strict_types=1);

return [
    'up' => 'create table users (id integer primary key, email varchar(255) not null)',
    'down' => 'drop table users',
];
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
Each migration runs inside a transaction where the database driver supports transactional DDL (SQLite and PostgreSQL do).
