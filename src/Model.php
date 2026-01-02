<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

/**
 * @template T
 */
interface Model
{
    /** @return T */
    public static function makeDefault();

    public static function getTableName(): string;

    // FIXNOW This is in practice used as both property and column, even though in theory property and column
    //   could have different names. There are some headaches in this distinction that haven't been worked through
    //   yet. Same with columns other than this one. (See WHERE conditions, etc.)
    public static function getPrimaryKeyColumn(): string;

    public function getPrimaryKey(): mixed;

    /** @return array<int|string, string> */
    public static function getColumns(): array;

    /**
     * @param array<string|int, mixed> $cell
     */
    public function readFromQueryResult(array $cell): void;

    /** @return array<string, mixed> */
    public function getWriteableValues(): array;
}
