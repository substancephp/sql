<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

use http\Header\Parser;
use SubstancePHP\SQL\Internal\Literal;

class Query
{
    public private(set) string $sql = '';

    /** @var mixed[] */
    public private(set) array $params = [];

    public function run(\PDO $pdo): \PDOStatement
    {
        $statement = $pdo->prepare($this->sql);
        foreach ($this->params as $i => $value) {
            $statement->bindValue($i + 1, $value, self::pdoType($value));
        }
        $statement->execute();
        return $statement;
    }

    private static function pdoType(mixed $value): int
    {
        return match (true) {
            \is_bool($value) => \PDO::PARAM_BOOL,
            ($value === null) => \PDO::PARAM_NULL,
            \is_int($value) => \PDO::PARAM_INT,
            default => \PDO::PARAM_STR,
        };
    }

    /** @return array<string, mixed>[] */
    public function fetchAll(\PDO $pdo): array
    {
        return $this->run($pdo)->fetchAll();
    }

    public function fetchColumn(\PDO $pdo): mixed
    {
        return $this->run($pdo)->fetchColumn();
    }

    /**
     * @param array<int|string, string> $columns
     *
     * Use like: Query::select(['fieldA', 'alias_for_fieldB' => 'fieldB'])
     */
    public static function select(array $columns): self
    {
        return new self()->appendSelect($columns);
    }

    /**
     * @param array<int|string, string> $columns
     *
     * Use like: ->select(['fieldA', 'alias_for_fieldB' => 'fieldB'])
     */
    public function appendSelect(array $columns): self
    {
        $this->append('select');
        $i = 0;
        foreach ($columns as $left => $right) {
            if ($i != 0) {
                $this->appendTight(',');
            }
            $this->append($right);
            if (! \is_int($left)) {
                $this->append('as')->append($left);
            }
            ++$i;
        }
        return $this;
    }

    public function from(string $table): self
    {
        return $this->append("from $table");
    }

    public function innerJoin(string $table): self
    {
        return $this->append("inner join $table");
    }

    public function leftJoin(string $table): self
    {
        return $this->append("left join $table");
    }

    /** @param string[] $fields */
    public function groupBy(array $fields): self
    {
        return $this->append('group by')->append(implode(', ', $fields));
    }

    public function on(string $condition): self
    {
        return $this->append("on ($condition)");
    }

    /** @param array<string, mixed> $criteria */
    public function where(
        array $criteria = [],
        string $comparator = '=',
        string $booleanOperator = 'and',
    ): self {
        return $this
            ->append('where')
            ->parens(fn (Query $q) => $q->buildCriteria($criteria, $comparator, $booleanOperator));
    }

    /** @param array<string, mixed> $criteria */
    public function andWhere(
        array $criteria = [],
        string $comparator = '=',
        string $booleanOperator = 'and',
    ): self {
        return $this
            ->append('and')
            ->parens(fn ($q) => $q->buildCriteria($criteria, $comparator, $booleanOperator));
    }

    /** @param array<string, mixed> $criteria */
    public function whereNot(array $criteria = []): self
    {
        return $this
            ->append('where not')
            ->parens(fn ($q) => $q->buildCriteria($criteria, '=', 'and'));
    }

    /** @param array<string, mixed> $criteria */
    public function andWhereNot(array $criteria = []): self
    {
        return $this
            ->append('and not')
            ->parens(fn ($q) => $q->buildCriteria($criteria, '=', 'and'));
    }

    /**
     * Use like
     *
     *   ->orderBy(['fieldA', 'fieldB' => Query::DESC])
     *
     * @param array<int|string, string> $fields
     * */
    public function orderBy(array $fields): self
    {
        $this->append('order by');
        $i = 0;
        foreach ($fields as $left => $right) {
            if ($i != 0) {
                $this->appendTight(',');
            }
            if (is_int($left)) {
                $this->append($right);
            } else {
                $this->append("$left $right");
            }
            ++$i;
        }
        return $this;
    }

    /**
     * Appends a parenthesized expression built by calling the Query passed to the callback.
     * The callback should accept a Query and return the same Query. It will in fact be passed *this* query.
     */
    public function parens(callable $callback): self
    {
        $this->append('(');
        $callback($this);
        return $this->append(')');
    }

    public function limit(int $limit): self
    {
        return $this->append('limit')->appendIntParam($limit);
    }

    public function offset(int $offset): self
    {
        return $this->append('offset')->appendIntParam($offset);
    }

    /** @param array<string, mixed> $criteria */
    private function buildCriteria(array $criteria, string $comparator, string $booleanOperator): self
    {
        $i = 0;
        foreach ($criteria as $field => $value) {
            if ($i != 0) {
                $this->append($booleanOperator);
            }
            $this->append($field);
            if ($value === null) {
                switch ($comparator) {
                    case '=':
                        $this->append('is null');
                        break;
                    case '!=':
                        // fallthrough
                    case '<>':
                        $this->append('is not null');
                        break;
                    default:
                        throw new \RuntimeException('invalid comparison with NULL');
                }
            } else {
                $this->append($comparator)->appendParam($value);
            }
            ++$i;
        }
        return $this;
    }

    public static function update(string $table): self
    {
        return new self()->appendUpdate($table);
    }

    public function appendUpdate(string $table): self
    {
        return $this->append("update $table");
    }

    /** @param array<string, mixed> $params */
    public function set(array $params): self
    {
        $this->append('set');
        $i = 0;
        foreach ($params as $field => $value) {
            if ($i != 0) {
                $this->appendTight(',');
            }
            $this->append($field)->append('=')->appendParam($value);
            ++$i;
        }
        return $this;
    }

    /** @param array<string, mixed> $values */
    public static function insertInto(string $table, array $values): self
    {
        return new self()->appendInsertInto($table, $values);
    }

    /** @param array<string, mixed> $values */
    public function appendInsertInto(string $table, array $values): self
    {
        $this->append("insert into $table (");
        $deferredPlaceholders = [];
        foreach ($values as $columnName => $value) {
            if (! empty($deferredPlaceholders)) {
                $this->appendTight(', ');
            }
            $this->appendTight($columnName);

            if ($value instanceof Literal) {
                $deferredPlaceholders[] = (string) $value;
            } else {
                $this->params[] = $value;
                $deferredPlaceholders[] = '?';
            }
        }
        $this->appendTight(') values (');
        $fragment = \implode(', ', $deferredPlaceholders);
        return $this->appendTight($fragment)->appendTight(')');
    }

    public static function deleteFrom(string $table): self
    {
        return new self()->appendDeleteFrom($table);
    }

    public function appendDeleteFrom(string $table): self
    {
        return $this->append("delete from $table");
    }

    /** @param string[] $fields */
    public function returning(array $fields): self
    {
        return $this->append('returning')->append(\implode(', ', $fields));
    }

    /** @param array<string, callable> $clauses */
    public static function with(array $clauses): self
    {
        return new self()->appendWith($clauses);
    }

    /** @param array<string, callable> $clauses */
    public function appendWith(array $clauses): self
    {
        $this->appendTight('with');
        $i = 0;
        foreach ($clauses as $label => $callback) {
            if ($i != 0) {
                $this->appendTight(',');
            }
            $this->append($label)->append('as')->parens(fn ($q) => $callback($q));
            ++$i;
        }
        return $this;
    }

    public static function literal(string $fragment): Literal
    {
        return new Literal($fragment);
    }

    public function append(string $fragment): self
    {
        if ($this->sql) {
            $this->appendTight(' ');
        }
        return $this->appendTight($fragment);
    }

    public function appendTight(string $fragment): self
    {
        $this->sql .= $fragment;
        return $this;
    }

    private function appendIntParam(int $value): self
    {
        $this->params[] = $value;
        return $this->append('?');
    }

    /**
     * @param bool|int|float|string|Literal|null|(bool|int|float|string|Literal|null)[] $value
     * @return $this
     */
    public function appendParam(bool|int|float|string|literal|null|array $value): self
    {
        if ($value instanceof Literal) {
            $this->append((string) $value);
        } elseif (\is_array($value)) {
            $this->parens(function (Query $q) use ($value) {
                foreach ($value as $i => $element) {
                    if ($i != 0) {
                        $q->appendTight(',');
                    }
                    $q->appendParam($element);
                }
            });
        } else {
            $this->params[] = $value;
            $this->append('?');
        }
        return $this;
    }
}
