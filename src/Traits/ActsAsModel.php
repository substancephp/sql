<?php

declare(strict_types=1);

namespace SubstancePHP\SQL\Traits;

use PhpParser\Node\Expr\AssignOp\Mod;
use SubstancePHP\SQL\Attributes\Column;
use SubstancePHP\SQL\Attributes\Table;
use SubstancePHP\SQL\ModelQuery;
use SubstancePHP\SQL\Noop;

trait ActsAsModel
{
    /**
     * @throws \Exception
     */
    public static function getTableName(): string
    {
        return self::getTableAttributeInstance()->name;
    }

    public static function getPrimaryKeyColumn(): string
    {
        return self::getTableAttributeInstance()->primaryKey;
    }

    private static function getTableAttributeInstance(): Table
    {
        static $result;
        return $result ??= (function () {
            $reflectionClass = new \ReflectionClass(self::class);
            $attributes = $reflectionClass->getAttributes(Table::class);
            if (\count($attributes) == 0) {
                throw new \Exception($reflectionClass->getName() . ' does not have attribute ' . Table::class);
            }
            return $attributes[0]->newInstance();
        })();
    }

    public function getPrimaryKey(): mixed
    {
        return $this->{self::getPrimaryKeyColumn()};
    }

    /** @return string[] */
    public static function getColumns(): array
    {
        static $columns;
        return $columns ??= (function () {
            $reflection = new \ReflectionClass(self::class);
            $reflectionProperties = $reflection->getProperties();
            $columns = [];
            foreach ($reflectionProperties as $reflectionProperty) {
                $attributes = $reflectionProperty->getAttributes(Column::class);
                if (\count($attributes) == 0) {
                    continue;
                }
                $attribute = $attributes[0]->newInstance();
                $columnName = $attribute->name;
                $propertyName = $reflectionProperty->getName();
                if ($columnName === $propertyName) {
                    // No need for alias
                    $columns[] = $columnName;
                } else {
                    // Property name will be used as alias in queries
                    $columns[$propertyName] = $columnName;
                }
            }
            return $columns;
        })();
    }

    /** @param array<int|string, mixed> $cell */
    public function readFromQueryResult(array $cell): void
    {
        foreach ($cell as $k => $v) {
            if (\is_string($k)) {
                $this->{$k} = $v;
            }
        }
    }

    /** @return array<string, mixed> */
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

    public static function makeDefault(): self
    {
        return new self();
    }

    /** @return ModelQuery<$this> */
    public function insert(): ModelQuery
    {
        return ModelQuery::insert($this);
    }

    /** @return ModelQuery<self> */
    public static function find(mixed $primaryKey): ModelQuery
    {
        return ModelQuery::find(self::class, $primaryKey);
    }

    /** @return ModelQuery<self> */
    public static function selectAll(): ModelQuery
    {
        return ModelQuery::selectFrom(self::class);
    }

    /** @return ModelQuery<$this> */
    public function update(): ModelQuery
    {
        return ModelQuery::update($this);
    }

    /** @return ModelQuery<$this> */
    public function save(): ModelQuery
    {
        return ModelQuery::save($this);
    }

    /** @return ModelQuery<$this> */
    public function delete(): ModelQuery
    {
        return ModelQuery::delete($this);
    }
}
