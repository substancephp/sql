<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

final class Migration
{
    /** @var \Closure */
    private \Closure $migrate;

    /** @var \Closure */
    private \Closure $rollback;

    /**
     * @param callable(\PDO): void $migrate
     * @param callable(\PDO): void $rollback
     */
    public function __construct(callable $migrate, callable $rollback)
    {
        $this->migrate = \Closure::fromCallable($migrate);
        $this->rollback = \Closure::fromCallable($rollback);
    }

    public function migrate(\PDO $pdo): void
    {
        ($this->migrate)($pdo);
    }

    public function rollback(\PDO $pdo): void
    {
        ($this->rollback)($pdo);
    }
}
