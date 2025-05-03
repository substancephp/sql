<?php

declare(strict_types=1);

namespace TestUtil\Fixture;

use SubstancePHP\SQL\Model;
use SubstancePHP\SQL\Noop;

/**
 * @implements Model<self>
 */
class Vehicle implements Model
{
    public Noop|int $id = Noop::T;
    public Noop|string $kind = Noop::T;
    public Noop|string $make = Noop::T;
    public Noop|string $model = Noop::T;
    public Noop|int $year = Noop::T;
    public Noop|string|null $briefDescription = Noop::T;

    #[\Override]
    public static function makeDefault()
    {
        return new self();
    }

    #[\Override]
    public static function getTableName(): string
    {
        return 'vehicles';
    }

    #[\Override]
    public static function getPrimaryKeyColumn(): string
    {
        return 'id';
    }

    /**
     * @throws \Exception
     */
    #[\Override]
    public function getPrimaryKey(): int
    {
        if ($this->id === Noop::T) {
            throw new \Exception();
        }
        return $this->id;
    }

    #[\Override]
    public static function getColumns(): array
    {
        return [
            'id',
            'kind',
            'make',
            'model',
            'year',
            'briefDescription' => 'brief_description',
        ];
    }

    #[\Override]
    public function readFromQueryResult(array $cell): void
    {
        foreach ($cell as $k => $v) {
            if (\is_string($k)) {
                $this->{$k} = $v;
            }
        }
    }

    #[\Override]
    public function getWriteableValues(): array
    {
        $values = [];
        foreach (self::getColumns() as $k => $v) {
            if (\is_string($k)) {
                if ($this->{$k} !== Noop::T) {
                    $values[$v] = $this->{$k};
                }
            } else {
                if ($this->{$v} !== Noop::T) {
                    $values[$v] = $this->{$v};
                }
            }
        }
        return $values;
    }
}
