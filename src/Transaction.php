<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

use SubstancePHP\SQL\Internal\TransactionState;

/**
 * Runs a callback inside a database transaction.
 *
 * Nesting is handled automatically. The outermost scope begins and commits a real transaction, while scopes
 * nested inside it are delimited by savepoints, so an inner scope can be rolled back on its own without
 * discarding the work of the scope enclosing it. If a transaction is already in progress on the connection
 * when the outermost scope begins, it is adopted rather than replaced, and left for its owner to commit.
 *
 * Nesting is implemented using savepoints, so it requires a database that supports them.
 */
final class Transaction
{
    private bool $finished = false;

    private function __construct(
        private \PDO $pdo,
        private TransactionState $state,
        private ?string $savepoint,
    ) {
    }

    /**
     * Runs the callback inside a transaction, which is committed if the callback returns, and rolled back
     * if it throws. Returns whatever the callback returns.
     *
     * @template T
     * @param callable(\PDO): T $callback
     * @return T
     */
    public static function run(\PDO $pdo, callable $callback): mixed
    {
        $transaction = self::begin($pdo);
        try {
            $result = $callback($pdo);
        } catch (\Throwable $throwable) {
            $transaction->rollback($throwable);
            throw $throwable;
        }
        try {
            $transaction->commit();
        } catch (\Throwable $throwable) {
            // Leaving the work of a scope that could not be committed to be committed by an enclosing
            // scope, or by nobody at all, would be worse than undoing it.
            $transaction->rollback($throwable);
            throw $throwable;
        }
        return $result;
    }

    private static function begin(\PDO $pdo): self
    {
        $state = TransactionState::for($pdo);
        // Only an outermost scope owns a real transaction, and only if the caller hasn't started one.
        $ownsTransaction = (($state->depth() === 0) && ! $pdo->inTransaction());
        if ($ownsTransaction) {
            if ($pdo->beginTransaction() === false) {
                throw new \RuntimeException('Could not begin a transaction.');
            }
            $savepoint = null;
        } else {
            if (! $pdo->inTransaction()) {
                throw new \RuntimeException(
                    'Transaction state out of sync: the connection is no longer in a transaction.',
                );
            }
            $savepoint = $state->nextSavepointName();
            self::exec($pdo, "savepoint $savepoint");
        }
        $state->enter();
        return new self($pdo, $state, $savepoint);
    }

    private function commit(): void
    {
        if (! $this->pdo->inTransaction()) {
            throw new \RuntimeException(
                'The transaction ended before this scope could be committed. A statement within it may '
                    . 'have caused an implicit commit.',
            );
        }
        // The scope is finished only once the commit has succeeded, so that a failed commit still leaves
        // the scope to be rolled back.
        if ($this->savepoint === null) {
            if ($this->pdo->commit() === false) {
                throw new \RuntimeException('Could not commit the transaction.');
            }
        } else {
            self::exec($this->pdo, "release savepoint {$this->savepoint}");
        }
        $this->finish();
    }

    /** Rolls back this scope, reporting any failure to do so with the given cause chained to it. */
    private function rollback(\Throwable $cause): void
    {
        if ($this->finished) {
            return;
        }
        // Unlike a failed commit, a failed rollback is not worth reattempting, so the scope is finished up
        // front, whatever the outcome below.
        $this->finish();
        if (! $this->pdo->inTransaction()) {
            // The transaction has already ended, so there is nothing left for this scope to roll back.
            return;
        }
        try {
            if ($this->savepoint === null) {
                if ($this->pdo->rollBack() === false) {
                    throw new \RuntimeException('Could not roll back the transaction.');
                }
            } else {
                self::exec($this->pdo, "rollback to savepoint {$this->savepoint}");
                self::exec($this->pdo, "release savepoint {$this->savepoint}");
            }
        } catch (\Throwable $failure) {
            // Reported in place of the cause, which is chained to it, as an unsuccessful rollback is the
            // more serious of the two.
            throw new \RuntimeException(
                'Could not roll back after a failure: ' . $failure->getMessage(),
                previous: $cause,
            );
        }
    }

    private function finish(): void
    {
        $this->finished = true;
        $this->state->leave();
    }

    private static function exec(\PDO $pdo, string $sql): void
    {
        if ($pdo->exec($sql) === false) {
            $errorInfo = $pdo->errorInfo();
            throw new \RuntimeException('Transaction statement failed: ' . ($errorInfo[2] ?? 'unknown'));
        }
    }
}
