<?php

declare(strict_types=1);

namespace SubstancePHP\SQL;

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
     * Starts a query with the given select expression, e.g. `count(*)`, and any parameters it uses.
     *
     * Use like: `Query::selectExpression('count(*)')->from('users')`.
     *
     * @param mixed[] $params
     */
    public static function selectExpression(string $expression, array $params = []): self
    {
        $query = new self();
        $query->append('select')->append($expression);
        $query->params = $params;
        return $query;
    }

    public static function selectCount(string $expression = '*'): self
    {
        return self::selectExpression("count($expression)");
    }

    public static function selectSum(string $field): self
    {
        return self::selectExpression("sum($field)");
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
                $this->append('as')->append(self::quotedAliasIfNeeded($left));
            }
            ++$i;
        }
        return $this;
    }

    /**
     * Quotes an alias unless it consists only of lowercase letters, digits and underscores. Aliases like
     * `userId` need quoting because unquoted identifiers are folded to lowercase on some engines, e.g.
     * PostgreSQL, so the result row key would otherwise no longer match the alias.
     */
    private static function quotedAliasIfNeeded(string $alias): string
    {
        if (\preg_match('/^[a-z0-9_]+$/', $alias) === 1) {
            return $alias;
        }
        return '"' . \str_replace('"', '""', $alias) . '"';
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
            } elseif ($value instanceof \Closure) {
                // A closure value builds the comparison's right-hand side itself, which lets callers
                // express function-wrapped comparisons while keeping values bound as parameters.
                $this->append($comparator);
                $value($this);
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

    /**
     * Inserts multiple rows in chunks, as a sequence of multi-row `insert` statements.
     *
     * @param string[] $columns
     * @param iterable<array<string, mixed>> $rows
     * @return int The number of rows inserted.
     */
    public static function insertRows(
        \PDO $pdo,
        string $table,
        array $columns,
        iterable $rows,
        int $chunkSize,
    ): int {
        if ($columns === []) {
            throw new \InvalidArgumentException('Columns must not be empty.');
        }
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        $columnList = \implode(', ', $columns);
        $placeholder = (new self())
            ->parens(fn (Query $q) => $q->append(\implode(', ', \array_fill(0, \count($columns), '?'))))
            ->sql;
        $inserted = 0;
        $chunk = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (! \array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("Row is missing column: $column");
                }
            }
            $chunk[] = $row;
            if (\count($chunk) === $chunkSize) {
                $inserted += self::insertOneChunk($pdo, $table, $columnList, $placeholder, $columns, $chunk);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            $inserted += self::insertOneChunk($pdo, $table, $columnList, $placeholder, $columns, $chunk);
        }
        return $inserted;
    }

    /**
     * @param string[] $columns
     * @param array<string, mixed>[] $rows
     */
    private static function insertOneChunk(
        \PDO $pdo,
        string $table,
        string $columnList,
        string $placeholder,
        array $columns,
        array $rows,
    ): int {
        $query = new self();
        $query->append("insert into $table ($columnList) values");
        $placeholderGroups = [];
        foreach ($rows as $row) {
            $placeholderGroups[] = $placeholder;
            foreach ($columns as $column) {
                $query->params[] = $row[$column];
            }
        }
        $query->appendTight(\implode(', ', $placeholderGroups));
        return $query->run($pdo)->rowCount();
    }

    /**
     * Appends a PostgreSQL-style `on conflict ... do update` clause to an insert query.
     *
     * @param string[] $conflictColumns
     * @param string[]|null $updateColumns
     */
    public function onConflictUpdate(array $conflictColumns, ?array $updateColumns = null): self
    {
        $updateColumns ??= $conflictColumns;
        $this->append('on conflict');
        $this->parens(fn (Query $q) => $q->append(\implode(', ', $conflictColumns)));
        $this->append('do update set');
        $assignments = [];
        foreach ($updateColumns as $column) {
            $assignments[] = "$column = excluded.$column";
        }
        return $this->appendTight(' ' . \implode(', ', $assignments));
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
    public function appendParam(bool|int|float|string|Literal|null|array $value): self
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
