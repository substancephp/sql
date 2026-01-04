<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

/** @template T of TableSchema<T> */
abstract class Update
{
    /**
     * @param TableSchema<T> $tableSchema
     */
    public function __construct(protected TableSchema $tableSchema)
    {
    }

    /**
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getData(): array
    {
        $result = [];
        $column = $this->tableSchema->getColumns();
        foreach ($column as $key => $columnName) {
            $propertyName = (\is_int($key) ? $columnName : $key);
            if (\property_exists($this, $propertyName)) {
                $propertyValue = $this->{$propertyName};
                if ($propertyValue !== Noop::T) {
                    $result[$columnName] = $propertyValue;
                }
            } else {
                $className = self::class;
                throw new \Exception("Property $propertyName does not exist on $className instance");
            }
        }
        return $result;
    }
}