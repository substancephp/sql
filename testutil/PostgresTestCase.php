<?php

declare(strict_types=1);

namespace TestUtil;

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that require a live PostgreSQL connection.
 *
 * The connection is configured with the SUBSTANCEPHP_SQL_POSTGRES_DSN environment variable. When the DSN
 * is absent, the test is marked skipped, so the SQLite-only local development flow keeps working.
 */
abstract class PostgresTestCase extends TestCase
{
    private ?\PDO $pdo = null;

    #[\Override]
    protected function setUp(): void
    {
        $dsn = \getenv('SUBSTANCEPHP_SQL_POSTGRES_DSN');
        if ($dsn === false || $dsn === '') {
            $this->markTestSkipped('SUBSTANCEPHP_SQL_POSTGRES_DSN is not set.');
        }
        $this->pdo = new \PDO($dsn);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();
        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $pdo = $this->pdo;
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $this->pdo = null;
        parent::tearDown();
    }

    protected function pdo(): \PDO
    {
        if ($this->pdo === null) {
            throw new \RuntimeException('No PostgreSQL connection. Was the test skipped?');
        }
        return $this->pdo;
    }

    protected function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }
}
