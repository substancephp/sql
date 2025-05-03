<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

use PhpParser\Node\Expr\AssignOp\Mod;
use SubstancePHP\SQL\Internal\Literal;

/**
 * @template T of Model
 */
class ModelQuery
{
    private Query $query;

    /** @var class-string<T> */
    private string $class;

    /**
     * @param class-string<T> $class
     */
    public function __construct(string $class)
    {
        $this->query = new Query();
        $this->class = $class;
    }

    public function run(\PDO $pdo): \PDOStatement
    {
        return $this->query->run($pdo);
    }

    public function getSql(): string
    {
        return $this->query->sql;
    }

    /**
     * @return ?T
     * @throws \ReflectionException
     */
    public function first(\PDO $pdo)
    {
        return $this->fetch($pdo)[0] ?? null;
    }

    /**
     * @param \PDO $pdo
     * @return T[]
     * @throws \ReflectionException
     */
    public function fetch(\PDO $pdo): array
    {
        $rows = $this->query->run($pdo)->fetchAll();
        $class = $this->class;
        return \array_map(function (array $row) use ($class) {
            $instance = $class::makeDefault();
            $instance->readFromQueryResult($row);
            return $instance;
        }, $rows);
    }

    /**
     * @template M of Model
     * @param class-string<M> $class
     * @return self<M>
     */
    public static function find(string $class, mixed $primaryKey): self
    {
        return self::selectFrom($class)->where([$class::getPrimaryKeyColumn() => $primaryKey]);
    }

    /**
     * @template M of Model
     * @param class-string<M> $class
     * @return self<M>
     */
    public static function selectFrom(string $class): self
    {
        $modelQuery = new self($class);
        $readableColumns = $class::getColumns();
        $selectedColumns = [];
        $tableName = $class::getTableName();
        foreach ($readableColumns as $k => $v) {
            $selectedColumns[$k] = "$tableName.$v";
        }
        $modelQuery->query->appendSelect($selectedColumns);
        $modelQuery->query->from($tableName);
        return $modelQuery;
    }

    /** @return $this */
    public function innerJoin(string $table): self
    {
        $this->query->innerJoin($table);
        return $this;
    }

    /** @return $this */
    public function leftJoin(string $table): self
    {
        $this->query->leftJoin($table);
        return $this;
    }

    /**
     * @param string[] $fields
     * @return $this
     */
    public function groupBy(array $fields): self
    {
        $this->query > $this->groupBy($fields);
        return $this;
    }

    /** @return $this */
    public function on(string $condition): self
    {
        $this->query->on($condition);
        return $this;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return $this
     */
    public function where(
        array $criteria = [],
        string $comparator = '=',
        string $booleanOperator = 'and',
    ): self {
        $this->query->where($criteria, $comparator, $booleanOperator);
        return $this;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return $this
     */
    public function andWhere(
        array $criteria = [],
        string $comparator = '=',
        string $booleanOperator = 'and',
    ): self {
        $this->query->andWhere($criteria, $comparator, $booleanOperator);
        return $this;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return $this
     */
    public function whereNot(array $criteria = []): self
    {
        $this->query->whereNot($criteria);
        return $this;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return $this
     */
    public function andWhereNot(array $criteria = []): self
    {
        $this->query->andWhereNot($criteria);
        return $this;
    }

    /**
     * Use like
     *
     *   ->orderBy(['fieldA', 'fieldB' => Query::DESC])
     *
     * @param array<int|string, string> $fields
     * @return $this
     */
    public function orderBy(array $fields): self
    {
        $this->query->orderBy($fields);
        return $this;
    }

    /**
     * Appends a parenthesized expression built by calling the Query passed to the callback.
     * The callback should accept a Query and return the same Query. It will in fact be passed *this* query.
     *
     * @return $this
     */
    public function parens(callable $callback): self
    {
        $this->query->parens($callback);
        return $this;
    }

    /** @return $this */
    public function limit(int $limit): self
    {
        $this->query->limit($limit);
        return $this;
    }

    /** @return $this */
    public function offset(int $offset): self
    {
        $this->query->offset($offset);
        return $this;
    }

    /**
     * @template M of Model
     * @param M $model
     * @return self<M>
     */
    public static function patch($model): self
    {
        $class = \get_class($model);
        $modelQuery = new self($class);
        $updates = $model->getWriteableValues();
        unset($updates[$class::getPrimaryKeyColumn()]);
        $modelQuery->query
            ->appendUpdate($class::getTableName())
            ->set($updates)
            ->where([$class::getPrimaryKeyColumn() => $model->getPrimaryKey()]);
        return $modelQuery;
    }

    /**
     * @template M of Model
     * @param M $model
     * @return self<M>
     */
    public static function insert($model): self
    {
        $class = \get_class($model);
        $modelQuery = new self($class);
        $modelQuery->query->appendInsertInto($class::getTableName(), $model->getWriteableValues());
        return $modelQuery;
    }

    /**
     * @param class-string<T> $class
     * @return self<T>
     */
    public static function deleteFrom(string $class): self
    {
        $modelQuery = new self($class);
        $modelQuery->query->appendDeleteFrom($class::getTableName());
        return $modelQuery;
    }

    /**
     * @param string[] $fields
     * @return $this
     */
    public function returning(array $fields): self
    {
        $this->query->returning($fields);
        return $this;
    }

    /**
     * @param array<string, callable> $clauses
     * @return $this
     */
    public function appendWith(array $clauses): self
    {
        $this->query->appendWith($clauses);
        return $this;
    }

    public static function literal(string $fragment): Literal
    {
        return new Literal($fragment);
    }

    /** @return $this */
    public function append(string $fragment): self
    {
        $this->query->append($fragment);
        return $this;
    }

    /** @return $this */
    public function appendTight(string $fragment): self
    {
        $this->query->appendTight($fragment);
        return $this;
    }

    /**
     * @param bool|int|float|string|Literal|null|(bool|int|float|string|Literal|null)[] $value
     * @return $this
     */
    public function appendParam(bool|int|float|string|literal|null|array $value): self
    {
        $this->query->appendParam($value);
        return $this;
    }
}
