<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

final class MigrationRunner
{
    private const DEFAULT_TABLE = 'migrations';

    public function __construct(
        private \PDO $pdo,
        private string $directory,
        private string $table = self::DEFAULT_TABLE,
    ) {
        self::assertValidTableName($table);
        if (! \is_dir($directory)) {
            throw new \InvalidArgumentException("Migrations directory does not exist: $directory");
        }
    }

    /**
     * Apply pending migrations.
     *
     * @return string[] Names of the migrations that were applied.
     */
    public function migrate(?int $steps = null): array
    {
        if ($steps !== null && $steps < 0) {
            throw new \InvalidArgumentException('Number of steps must not be negative.');
        }
        $this->ensureSchema();
        $this->assertAppliedMigrationsArePrefixOfFiles();
        $targets = $this->getPendingMigrations();
        if ($steps !== null) {
            $targets = \array_slice($targets, 0, $steps);
        }
        foreach ($targets as $name) {
            $migration = $this->loadMigration($name);
            $this->runInTransaction(function () use ($migration, $name): void {
                $migration->migrate($this->pdo);
                $this->recordMigration($name);
            });
        }
        return $targets;
    }

    /**
     * Revert applied migrations, most recent first.
     *
     * @return string[] Names of reverted migrations.
     */
    public function rollback(int $steps = 1): array
    {
        if ($steps < 0) {
            throw new \InvalidArgumentException('Number of steps must not be negative.');
        }
        if ($steps == 0) {
            return [];
        }
        $applied = $this->getAppliedMigrations();
        $targets = \array_slice(\array_reverse($applied), 0, $steps);
        foreach ($targets as $name) {
            $migration = $this->loadMigration($name);
            $this->runInTransaction(function () use ($migration, $name): void {
                $migration->rollback($this->pdo);
                $this->removeMigration($name);
            });
        }
        return $targets;
    }

    /** @return list<array{name: string, status: 'applied'|'pending', applied_at: ?string}> */
    public function status(): array
    {
        $applied = $this->fetchAppliedRows();
        $files = $this->getMigrationFiles();
        $missing = \array_diff(\array_keys($applied), \array_keys($files));
        if (\count($missing) != 0) {
            throw new \RuntimeException(
                'Applied migrations are missing from disk: ' . \implode(', ', $missing),
            );
        }
        $rows = [];
        foreach (\array_keys($files) as $name) {
            $rows[] = [
                'name' => $name,
                'status' => isset($applied[$name]) ? 'applied' : 'pending',
                'applied_at' => $applied[$name] ?? null,
            ];
        }
        return $rows;
    }

    /** @return list<string> Names of applied migrations, in application order. */
    public function getAppliedMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $applied = \array_fill_keys(\array_keys($this->fetchAppliedRows()), true);
        $missing = \array_diff(\array_keys($applied), \array_keys($files));
        if (\count($missing) != 0) {
            throw new \RuntimeException(
                'Applied migrations are missing from disk: ' . \implode(', ', $missing),
            );
        }
        $appliedInOrder = [];
        foreach (\array_keys($files) as $name) {
            if (isset($applied[$name])) {
                $appliedInOrder[] = $name;
            }
        }
        return $appliedInOrder;
    }

    /** @return list<string> Names of pending migrations, in application order. */
    public function getPendingMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $applied = \array_fill_keys(\array_keys($this->fetchAppliedRows()), true);
        $missing = \array_diff(\array_keys($applied), \array_keys($files));
        if (\count($missing) != 0) {
            throw new \RuntimeException(
                'Applied migrations are missing from disk: ' . \implode(', ', $missing),
            );
        }
        $pending = [];
        foreach (\array_keys($files) as $name) {
            if (!isset($applied[$name])) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    private function ensureSchema(): void
    {
        $sql = "create table if not exists {$this->table} (" .
            "migration text not null primary key, " .
            "applied_at timestamp not null default current_timestamp" .
            ")";
        self::execSql($this->pdo, $sql);
    }

    /** @return array<string, string> Map of migration name to applied timestamp. */
    private function fetchAppliedRows(): array
    {
        $this->ensureSchema();
        $statement = $this->pdo->query("select migration, applied_at from {$this->table}");
        if ($statement === false) {
            throw new \RuntimeException('Failed to query the migrations table.');
        }
        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[(string) $row['migration']] = (string) $row['applied_at'];
        }
        return $rows;
    }

    /** @return array<string, string> Map of migration name to file path, in migration order. */
    private function getMigrationFiles(): array
    {
        $files = \glob(\rtrim($this->directory, '/') . '/*.php') ?: [];
        $files = \array_values(\array_filter($files, 'is_file'));
        \natsort($files);
        $migrationFiles = [];
        foreach ($files as $file) {
            $migrationFiles[\basename($file, '.php')] = $file;
        }
        return $migrationFiles;
    }

    private function loadMigration(string $name): Migration
    {
        $toLoad = $this->getMigrationFiles()[$name] ??
            throw new \RuntimeException("Missing migration: $name");
        $migration = require $toLoad;
        if (! ($migration instanceof Migration)) {
            throw new \RuntimeException("Migration file $toLoad must return a " . Migration::class . " instance.");
        }
        return $migration;
    }

    private function assertAppliedMigrationsArePrefixOfFiles(): void
    {
        $applied = \array_fill_keys(\array_keys($this->fetchAppliedRows()), true);
        $seenPending = false;
        foreach (\array_keys($this->getMigrationFiles()) as $name) {
            if (isset($applied[$name])) {
                if ($seenPending) {
                    throw new \RuntimeException(
                        "Cannot migrate: applied migrations are out of order. "
                            . "A pending migration appears before applied migration $name.",
                    );
                }
            } else {
                $seenPending = true;
            }
        }
    }

    private function runInTransaction(callable $callback): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction && ($this->pdo->beginTransaction() === false)) {
            throw new \RuntimeException('Could not start a transaction.');
        }
        try {
            $callback();
            if ($ownsTransaction && ($this->pdo->commit() === false)) {
                throw new \RuntimeException('Could not commit the transaction.');
            }
        } catch (\Throwable $throwable) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    private function recordMigration(string $name): void
    {
        $statement = $this->pdo->prepare("insert into {$this->table} (migration) values (?)");
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare the migration record insertion.');
        }
        $statement->bindValue(1, $name);
        if ($statement->execute() === false) {
            throw new \RuntimeException('Failed to record the migration.');
        }
    }

    private function removeMigration(string $name): void
    {
        $statement = $this->pdo->prepare("delete from {$this->table} where migration = ?");
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare the migration record removal.');
        }
        $statement->bindValue(1, $name);
        if ($statement->execute() === false) {
            throw new \RuntimeException('Failed to remove the migration record.');
        }
    }

    private static function assertValidTableName(string $table): void
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $table) !== 1) {
            throw new \InvalidArgumentException("Invalid migrations table name: $table");
        }
    }

    private static function execSql(\PDO $pdo, string $sql): void
    {
        if ($pdo->exec($sql) === false) {
            $errorInfo = $pdo->errorInfo();
            throw new \RuntimeException('Migration SQL failed: ' . ($errorInfo[2] ?? 'unknown error'));
        }
    }
}
