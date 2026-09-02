<?php

declare(strict_types=1);

namespace SubstancePHP\SQL\Traits;

use SubstancePHP\SQL\Attributes\Column;
use SubstancePHP\SQL\Attributes\Table;
use SubstancePHP\SQL\ModelQuery;

trait ActsAsModel
{
    /** @var array<string, \ReflectionProperty> */
    private static array $reflectionProperties = [];

    /** @throws \Exception */
    public static function getTableName(): string
    {
        return self::getTableAttributeInstance()->name;
    }

    public static function getPrimaryKeyColumn(): ?string
    {
        $property = self::getPrimaryKeyProperty();
        if ($property === null) {
            return null;
        }
        $reflectionProperty = self::$reflectionProperties[$property]
            ??= new \ReflectionProperty(self::class, $property);
        $attributes = $reflectionProperty->getAttributes(Column::class);
        if (\count($attributes) == 0) {
            throw new \Exception(
                self::class . '::$' . $property . ' must have a ' . Column::class . ' attribute.',
            );
        }
        return $attributes[0]->newInstance()->name;
    }

    public static function getPrimaryKeyProperty(): ?string
    {
        return 'id';
    }

    private static function getTableAttributeInstance(): Table
    {
        static $result;
        return $result ??= (function () {
            $reflectionClass = new \ReflectionClass(self::class);
            $attributes = $reflectionClass->getAttributes(Table::class);
            if (\count($attributes) == 0) {
                throw new \Exception(
                    $reflectionClass->getName() . ' does not have attribute ' . Table::class,
                );
            }
            return $attributes[0]->newInstance();
        })();
    }

    public function getPrimaryKey(): mixed
    {
        $property = self::getPrimaryKeyProperty();
        if ($property === null) {
            throw new \LogicException(self::class . ' has no primary key.');
        }
        return $this->{$property};
    }

    /** Whether the given property has been initialized (present), as opposed to absent. */
    public function propertyIsInitialized(string $property): bool
    {
        $reflectionProperty = self::$reflectionProperties[$property]
            ??= new \ReflectionProperty(self::class, $property);
        return $reflectionProperty->isInitialized($this);
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
        foreach (self::getColumns() as $key => $column) {
            $property = (\is_int($key) ? $column : $key);
            if (\array_key_exists($property, $cell)) {
                $this->{$property} = $cell[$property];
            }
        }
    }

    /** @return array<string, mixed> */
    public function getWriteableValues(): array
    {
        $values = [];
        $initializedProperties = \get_object_vars($this);
        foreach (self::getColumns() as $key => $column) {
            $property = (\is_int($key) ? $column : $key);
            if (\array_key_exists($property, $initializedProperties)) {
                $values[$column] = $this->{$property};
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
