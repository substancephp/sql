<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SubstancePHP\SQL\Internal\TransactionState;
use SubstancePHP\SQL\Transaction;

#[CoversClass(Transaction::class)]
#[CoversClass(TransactionState::class)]
final class TransactionTest extends TestCase
{
    private \PDO $pdo;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('create table t (x integer)');
        parent::setUp();
    }

    /** Read from the internal state, as the nesting depth is deliberately not part of the public API. */
    private static function depth(\PDO $pdo): int
    {
        return TransactionState::for($pdo)->depth();
    }

    private function insert(int $x): void
    {
        $this->pdo->exec("insert into t values ($x)");
    }

    /** @return int[] */
    private function rows(): array
    {
        $statement = $this->pdo->query('select x from t order by x');
        if ($statement === false) {
            throw new \RuntimeException('Failed to query the table.');
        }
        return \array_map('\intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    #[Test]
    public function commitsAndReturnsTheCallbackResult(): void
    {
        $result = Transaction::run($this->pdo, function (\PDO $pdo): string {
            $this->assertSame($this->pdo, $pdo);
            $this->assertSame(1, self::depth($pdo));
            $this->insert(1);
            return 'result';
        });

        $this->assertSame('result', $result);
        $this->assertSame([1], $this->rows());
        $this->assertSame(0, self::depth($this->pdo));
        $this->assertFalse($this->pdo->inTransaction());
    }

    #[Test]
    public function rollsBackAndRethrowsWhenTheCallbackThrows(): void
    {
        try {
            Transaction::run($this->pdo, function (): void {
                $this->insert(1);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame([], $this->rows());
        $this->assertSame(0, self::depth($this->pdo));
        $this->assertFalse($this->pdo->inTransaction());
    }

    #[Test]
    public function nestingRollsBackOnlyTheInnerScope(): void
    {
        Transaction::run($this->pdo, function (\PDO $pdo): void {
            $this->insert(1);
            try {
                Transaction::run($pdo, function (\PDO $pdo): void {
                    $this->assertSame(2, self::depth($pdo));
                    $this->insert(2);
                    throw new \RuntimeException('inner');
                });
            } catch (\RuntimeException $exception) {
                $this->assertSame('inner', $exception->getMessage());
            }
            $this->assertSame(1, self::depth($pdo));
            $this->insert(3);
        });

        $this->assertSame([1, 3], $this->rows());
        $this->assertSame(0, self::depth($this->pdo));
    }

    #[Test]
    public function nestedFailurePropagatesToTheEnclosingScope(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('inner');
        try {
            Transaction::run($this->pdo, function (\PDO $pdo): void {
                $this->insert(1);
                Transaction::run($pdo, function (): void {
                    $this->insert(2);
                    throw new \RuntimeException('inner');
                });
            });
        } finally {
            $this->assertSame([], $this->rows());
            $this->assertSame(0, self::depth($this->pdo));
        }
    }

    #[Test]
    public function nestsToArbitraryDepth(): void
    {
        Transaction::run($this->pdo, function (\PDO $pdo): void {
            Transaction::run($pdo, function (\PDO $pdo): void {
                Transaction::run($pdo, function (\PDO $pdo): void {
                    $this->assertSame(3, self::depth($pdo));
                    $this->insert(1);
                });
            });
        });

        $this->assertSame([1], $this->rows());
        $this->assertSame(0, self::depth($this->pdo));
    }

    #[Test]
    public function adoptsATransactionStartedByTheCaller(): void
    {
        $this->pdo->beginTransaction();

        Transaction::run($this->pdo, fn () => $this->insert(1));

        // The caller's transaction is left for them to finish, so their rollback still discards the work.
        $this->assertTrue($this->pdo->inTransaction());
        $this->assertSame([1], $this->rows());
        $this->pdo->rollBack();
        $this->assertSame([], $this->rows());
        $this->assertSame(0, self::depth($this->pdo));
    }

    #[Test]
    public function failsWhenTheTransactionEndedBeforeTheScopeCouldBeCommitted(): void
    {
        try {
            // As happens, for instance, when a statement causes an implicit commit, or when the
            // transaction is committed directly, as here.
            Transaction::run($this->pdo, function (\PDO $pdo): void {
                $this->insert(1);
                $pdo->commit();
            });
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'ended before this scope could be committed',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, self::depth($this->pdo));
        $this->assertFalse($this->pdo->inTransaction());
    }

    #[Test]
    public function detectsATransactionEndedBeforeANestedScopeBegins(): void
    {
        try {
            Transaction::run($this->pdo, function (\PDO $pdo): void {
                $pdo->commit();
                Transaction::run($pdo, fn () => $this->insert(1));
            });
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('out of sync', $exception->getMessage());
        }

        $this->assertSame(0, self::depth($this->pdo));
    }

    #[Test]
    public function reportsAFailedRollbackAndChainsTheOriginalFailureToIt(): void
    {
        Transaction::run($this->pdo, function (\PDO $pdo): void {
            try {
                Transaction::run($pdo, function (\PDO $pdo): void {
                    // Pulled out from under this scope, so that its rollback cannot succeed.
                    $pdo->exec('release savepoint substancephp_sp_1');
                    throw new \RuntimeException('original');
                });
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('roll back after a failure', $exception->getMessage());
                $this->assertSame('original', $exception->getPrevious()?->getMessage());
            }
        });

        $this->assertSame(0, self::depth($this->pdo));
    }

    #[Test]
    public function tracksStatePerConnection(): void
    {
        $other = new \PDO('sqlite::memory:');

        Transaction::run($this->pdo, function (\PDO $pdo) use ($other): void {
            $this->assertSame(1, self::depth($pdo));
            $this->assertSame(0, self::depth($other));
            $this->assertFalse($other->inTransaction());
        });

        $this->assertSame(0, self::depth($this->pdo));
    }
}
