<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

final class Migration
{
    /** @var \Closure */
    private \Closure $up;

    /** @var \Closure */
    private \Closure $down;

    /**
     * @param callable(\PDO): void $up
     * @param callable(\PDO): void $down
     */
    public function __construct(callable $up, callable $down)
    {
        $this->up = \Closure::fromCallable($up);
        $this->down = \Closure::fromCallable($down);
    }

    public function up(\PDO $pdo): void
    {
        ($this->up)($pdo);
    }

    public function down(\PDO $pdo): void
    {
        ($this->down)($pdo);
    }
}
