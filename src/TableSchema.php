<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

/** @template T */
abstract class TableSchema
{
    abstract public function getTableName(): string;

    // FIXNOW This is in practice used as both property and column, even though in theory property and column
    //   could have different names. There are some headaches in this distinction that haven't been worked through
    //   yet. Same with columns other than this one. (See WHERE conditions, etc.)
    public function getPrimaryKeyColumn(): string
    {
        return 'id';
    }

    /** @return array<int|string, string> */
    abstract public function getColumns(): array;
}
