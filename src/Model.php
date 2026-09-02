<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

/** @template T */
interface Model
{
    /** @return T */
    public static function makeDefault();

    public static function getTableName(): string;

    public static function getPrimaryKeyColumn(): string;

    public static function getPrimaryKeyProperty(): string;

    public function getPrimaryKey(): mixed;

    /** Whether the given property has been initialized (present), as opposed to absent. */
    public function propertyIsInitialized(string $property): bool;

    /** @return array<int|string, string> */
    public static function getColumns(): array;

    /** @param array<string|int, mixed> $cell */
    public function readFromQueryResult(array $cell): void;

    /** @return array<string, mixed> */
    public function getWriteableValues(): array;
}
