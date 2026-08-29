<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SubstancePHP\SQL\Migration;
use SubstancePHP\SQL\MigrationRunner;

#[CoversClass(MigrationRunner::class)]
#[CoversClass(Migration::class)]
final class MigrationRunnerTest extends TestCase
{
    private \PDO $pdo;
    private string $dir;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->dir = \sys_get_temp_dir() . '/substancephp_sql_migrations_' . \bin2hex(\random_bytes(6));
        \mkdir($this->dir);
        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (\glob($this->dir . '/*.php') ?: [] as $file) {
            \unlink($file);
        }
        if (\is_dir($this->dir)) {
            \rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function runner(string $table = 'migrations'): MigrationRunner
    {
        return new MigrationRunner($this->pdo, $this->dir, $table);
    }

    /** Write a migration file whose migrate/rollback closures run the given SQL. */
    private function writeSqlMigration(string $name, string $migrate, string $rollback): void
    {
        $template = <<<'PHP'
<?php

declare(strict_types=1);

return new \SubstancePHP\SQL\Migration(
    static function (\PDO $pdo): void {
        $pdo->exec(%s);
    },
    static function (\PDO $pdo): void {
        $pdo->exec(%s);
    },
);
PHP;
        $this->writeMigrationFile(
            $name,
            \sprintf($template, \var_export($migrate, true), \var_export($rollback, true)),
        );
    }

    private function writeMigrationFile(string $name, string $contents): void
    {
        \file_put_contents("$this->dir/$name.php", $contents);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare("select name from sqlite_master where type = 'table' and name = ?");
        $statement->execute([$table]);
        return $statement->fetchColumn() !== false;
    }

    private function migrationCount(): int
    {
        $statement = $this->pdo->query('select * from migrations');
        if ($statement === false) {
            throw new \RuntimeException('Failed to query the migrations table.');
        }
        return \count($statement->fetchAll());
    }

    #[Test]
    public function migrateAppliesAllPendingMigrationsAndRecordsThem(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->writeSqlMigration(
            '002_create_posts',
            'create table posts (id integer primary key autoincrement, title varchar(255))',
            'drop table posts',
        );

        $applied = $this->runner()->migrate();
        $this->assertSame(['001_create_users', '002_create_posts'], $applied);
        $this->assertTrue($this->tableExists('users'));
        $this->assertTrue($this->tableExists('posts'));
        $this->assertSame([], $this->runner()->getPendingMigrations());
        $this->assertSame(['001_create_users', '002_create_posts'], $this->runner()->getAppliedMigrations());
        $this->assertSame(2, $this->migrationCount());
    }

    #[Test]
    public function statusReportsPendingAndAppliedMigrations(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );

        $this->assertSame([
            ['name' => '001_create_users', 'status' => 'pending', 'applied_at' => null],
        ], $this->runner()->status());

        $this->runner()->migrate();
        $status = $this->runner()->status();
        $this->assertSame('001_create_users', $status[0]['name']);
        $this->assertSame('applied', $status[0]['status']);
        $this->assertIsString($status[0]['applied_at']);
    }

    #[Test]
    public function migrateAppliesOnlyTheGivenNumberOfSteps(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->writeSqlMigration(
            '002_create_posts',
            'create table posts (id integer primary key autoincrement, title varchar(255))',
            'drop table posts',
        );

        $applied = $this->runner()->migrate(1);
        $this->assertSame(['001_create_users'], $applied);
        $this->assertTrue($this->tableExists('users'));
        $this->assertFalse($this->tableExists('posts'));
        $this->assertSame(['002_create_posts'], $this->runner()->getPendingMigrations());
    }

    #[Test]
    public function migrateWithZeroStepsAppliesNothing(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key)',
            'drop table users',
        );

        $this->assertSame([], $this->runner()->migrate(0));
        $this->assertFalse($this->tableExists('users'));
        $this->assertSame(['001_create_users'], $this->runner()->getPendingMigrations());
    }

    #[Test]
    public function migrateRejectsNegativeSteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be negative');
        $this->runner()->migrate(-1);
    }

    #[Test]
    public function rollbackRevertsTheLastMigrationByDefault(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->writeSqlMigration(
            '002_create_posts',
            'create table posts (id integer primary key autoincrement, title varchar(255))',
            'drop table posts',
        );
        $this->runner()->migrate();

        $reverted = $this->runner()->rollback();
        $this->assertSame(['002_create_posts'], $reverted);
        $this->assertTrue($this->tableExists('users'));
        $this->assertFalse($this->tableExists('posts'));
        $this->assertSame(['001_create_users'], $this->runner()->getAppliedMigrations());
    }

    #[Test]
    public function rollbackRevertsTheGivenNumberOfMigrationsInReverseOrder(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->writeSqlMigration(
            '002_create_posts',
            'create table posts (id integer primary key autoincrement, title varchar(255))',
            'drop table posts',
        );
        $this->runner()->migrate();

        $reverted = $this->runner()->rollback(2);
        $this->assertSame(['002_create_posts', '001_create_users'], $reverted);
        $this->assertFalse($this->tableExists('users'));
        $this->assertFalse($this->tableExists('posts'));
        $this->assertSame([], $this->runner()->getAppliedMigrations());
    }

    #[Test]
    public function rollbackWithZeroStepsRevertsNothing(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key)',
            'drop table users',
        );
        $this->runner()->migrate();

        $this->assertSame([], $this->runner()->rollback(0));
        $this->assertTrue($this->tableExists('users'));
        $this->assertSame(['001_create_users'], $this->runner()->getAppliedMigrations());
    }

    #[Test]
    public function rollbackRejectsNegativeSteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be negative');
        $this->runner()->rollback(-1);
    }

    #[Test]
    public function migrationClosuresMayRunMultipleStatements(): void
    {
        $this->writeMigrationFile('001_create_two_tables', <<<'PHP'
<?php

declare(strict_types=1);

return new \SubstancePHP\SQL\Migration(
    static function (\PDO $pdo): void {
        $pdo->exec('create table alpha (id integer primary key)');
        $pdo->exec('create table beta (id integer primary key)');
    },
    static function (\PDO $pdo): void {
        $pdo->exec('drop table alpha');
        $pdo->exec('drop table beta');
    },
);
PHP);

        $this->assertSame(['001_create_two_tables'], $this->runner()->migrate());
        $this->assertTrue($this->tableExists('alpha'));
        $this->assertTrue($this->tableExists('beta'));

        $this->assertSame(['001_create_two_tables'], $this->runner()->rollback());
        $this->assertFalse($this->tableExists('alpha'));
        $this->assertFalse($this->tableExists('beta'));
    }

    #[Test]
    public function failedMigrationIsRolledBack(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->writeMigrationFile('002_bad', <<<'PHP'
<?php

declare(strict_types=1);

return new \SubstancePHP\SQL\Migration(
    static function (\PDO $pdo): void {
        $pdo->exec('create table should_not_exist (id integer primary key)');
        throw new \RuntimeException('boom');
    },
    static function (\PDO $pdo): void {
        $pdo->exec('drop table should_not_exist');
    },
);
PHP);

        try {
            $this->runner()->migrate();
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertTrue($this->tableExists('users'));
        $this->assertFalse($this->tableExists('should_not_exist'));
        $this->assertSame(['001_create_users'], $this->runner()->getAppliedMigrations());
    }

    #[Test]
    public function migrateRefusesOutOfOrderPendingMigrations(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->writeSqlMigration(
            '002_create_posts',
            'create table posts (id integer primary key autoincrement, title varchar(255))',
            'drop table posts',
        );
        $this->runner()->migrate();

        $this->writeSqlMigration(
            '000_late',
            'create table late (id integer primary key)',
            'drop table late',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('out of order');
        $this->runner()->migrate();
    }

    #[Test]
    public function missingAppliedMigrationFileCausesError(): void
    {
        $this->writeSqlMigration(
            '001_create_users',
            'create table users (id integer primary key autoincrement, name varchar(255))',
            'drop table users',
        );
        $this->runner()->migrate();
        \unlink($this->dir . '/001_create_users.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing from disk');
        $this->runner()->getAppliedMigrations();
    }

    #[Test]
    public function invalidMigrationFileIsRejected(): void
    {
        $this->writeMigrationFile('001_invalid', "<?php\n\nreturn 'not a migration';\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must return a');
        $this->runner()->migrate();
    }

    #[Test]
    public function constructorRejectsMissingDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MigrationRunner($this->pdo, $this->dir . '/does-not-exist');
    }

    #[Test]
    public function constructorRejectsInvalidTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MigrationRunner($this->pdo, $this->dir, 'not a valid table name');
    }
}
