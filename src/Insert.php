<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

/** @template T of TableSchema<T> */
abstract class Insert
{
    /**
     * @param TableSchema<T> $tableSchema
     */
    public function __construct(protected readonly TableSchema $tableSchema)
    {
    }

    /**
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function getData(): array
    {
        $result = [];
        $column = $this->tableSchema->getColumns();
        foreach ($column as $key => $columnName) {
            $propertyName = (\is_int($key) ? $columnName : $key);
            if (\property_exists($this, $propertyName)) {
                $result[$columnName] = $this->{$propertyName};
            } else {
                $className = self::class;
                throw new \Exception("Property $propertyName does not exist on $className instance");
            }
        }
        return $result;
    }

    public function run(\PDO $pdo): mixed
    {
        // FIXNOW This won't work with sqlite
        return Query::insertInto($this->tableSchema->getTableName(), $this->getData())
            ->returning([$this->tableSchema->getPrimaryKeyColumn()])
            ->fetchColumn($pdo);
    }
}