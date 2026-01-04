<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

interface Model
{
    public function getPrimaryKey(): mixed;

    /**
     * @param array<string|int, mixed> $cell
     */
    public function readFromQueryResult(array $cell): void;

    /** @return array<string, mixed> */
    public function getWriteableValues(): array;
}
