<?php

declare(strict_types=1);

namespace SubstancePHP\SQL\Internal;

use SubstancePHP\SQL\Transaction;

/**
 * Bookkeeping for the {@see Transaction} scopes open on a given connection.
 *
 * The state belongs to the connection rather than to any one {@see Transaction} instance, so that scopes
 * begun at unrelated call sites nest correctly.
 *
 * @internal
 */
final class TransactionState
{
    /** @var ?\WeakMap<\PDO, self> */
    private static ?\WeakMap $states = null;

    private int $depth = 0;

    private int $nextSavepoint = 1;

    public static function for(\PDO $pdo): self
    {
        self::$states ??= new \WeakMap();
        return self::$states[$pdo] ??= new self();
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function enter(): void
    {
        ++$this->depth;
    }

    public function leave(): void
    {
        --$this->depth;
    }

    /** Savepoint names are generated from a counter, so are always safe SQL identifiers. */
    public function nextSavepointName(): string
    {
        return 'substancephp_sp_' . $this->nextSavepoint++;
    }
}
