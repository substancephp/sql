<?php

declare(strict_types=1);

namespace Test;

use SubstancePHP\SQL\Query;
use PHPUnit\Framework\TestCase;

final class QueryTest extends TestCase
{
    private \PDO $pdo;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        parent::setUp();
    }

    private function createThingsTable(Query $query): Query
    {
        $query->append('create table things (x, y, z, t)')->run($this->pdo);
        return $query->clear();
    }

    public function testRunAndClear(): void
    {
        $query = new Query();
        $query->append('create TABLE things (x, y, z)')->run($this->pdo);
        $query->clear();
        $query->append('insert into things (x, y, z) values (10, 20, 30)')->run($this->pdo);
        $query->clear();
        $statement2 = $query->append('select * from things')->run($this->pdo);
        $results = $statement2->fetchAll();
        $this->assertSame([['x' => 10, 0 => 10, 'y' => 20, 1 => 20, 'z' => 30, 2 => 30]], $results);
    }

    public function testFetchAll(): void
    {
        $query = new Query();
        $query->append('select 1 as x');
        $results = $query->fetchAll($this->pdo);
        $this->assertSame(1, $results[0]['x']);
    }

    public function testFetchColumn(): void
    {
        $query = new Query();
        $query->append('select 1 as x');
        $result = $query->fetchColumn($this->pdo);
        $this->assertSame(1, $result);
    }

    public function testInsertSelectFrom(): void
    {
        $query = new Query();
        $this->createThingsTable($query);
        $query->insertInto('things', [
            'x' => 'hi',
            'y' => 30,
            'z' => null,
            't' => Query::literal("date('now')"),
        ])->run($this->pdo);
        $this->assertSame("insert into things (x, y, z, t) values (?, ?, ?, date('now'))", $query->sql);
        $query->clear()->insertInto('things', ['x' => 'nice', 'y' => 2, 'z' => 5.6])->run($this->pdo);
        $query->clear()->select(['x', 'y', 'stuff' => 'z'])->from('things');
        $this->assertSame('select x, y, z as stuff from things', $query->sql);
        $results = $query->fetchAll($this->pdo);
        $this->assertSame(2, count($results));
        $this->assertSame(2, $results[1]['y']);
        $this->assertSame(null, $results[0]['stuff']);
        $this->assertSame('nice', $results[1]['x']);
    }

    public function testInnerJoinOn(): void
    {
        $query = new Query();
        $query->select(['x', 'y', 'z'])->from('items')->innerJoin('cool_things')->on('whatever');
        $this->assertSame('select x, y, z from items inner join cool_things on (whatever)', $query->sql);
    }

    public function testLeftJoinOn(): void
    {
        $query = new Query();
        $query->select(['x', 'y', 'z'])->from('items')->leftJoin('cool_things')->on('whatever = hey.yo');
        $this->assertSame('select x, y, z from items left join cool_things on (whatever = hey.yo)', $query->sql);
    }

    public function testGroupBy(): void
    {
        $query = new Query();
        $query->groupBy(['x']);
        $this->assertSame('group by x', $query->sql);
        $query->clear()->groupBy(['y', 'z', 'africa']);
        $this->assertSame('group by y, z, africa', $query->sql);
    }

    public function testWhereAndCo(): void
    {
        $query = new Query();

        // basic case
        $query->select(['x', 'y'])->from('things')->where(['x' => 3, 'y' => 'cool']);
        $this->assertSame('select x, y from things where ( x = ? and y = ? )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $query->clear();

        // with different comparitor
        $query->select(['x', 'y'])->from('things')->where(['x' => 3, 'y' => 'cool'], '<=');
        $this->assertSame('select x, y from things where ( x <= ? and y <= ? )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $query->clear();

        // with different boolean operator, and throwing NULL into the mix
        $query->select(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '=', 'or');
        $this->assertSame('select x, y from things where ( x = ? or y = ? or z is null )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $this->assertFalse(isset($query->params[2]));
        $query->clear();

        // with NOT NULL (variant A)
        $query->select(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '<>', 'or');
        $this->assertSame('select x, y from things where ( x <> ? or y <> ? or z is not null )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $this->assertFalse(isset($query->params[2]));
        $query->clear();

        // with NOT NULL (variant B)
        $query->select(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '!=', 'or');
        $this->assertSame('select x, y from things where ( x != ? or y != ? or z is not null )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $this->assertFalse(isset($query->params[2]));
        $query->clear();

        // combine with andWhere
        $query->select(['x', 'y'])->from('things')
            ->whereNot(['x' => false, 'z' => null])
            ->andWhere(['fun' => 'great', 'things' => false], '>', 'or')
            ->andWhereNot(['x' => 3]);
        $this->assertSame('select x, y from things where not ( x = ? and z is null ) and ' .
            '( fun > ? or things > ? ) and not ( x = ? )', $query->sql);

        // with NOT NULL (invalid comparison)
        $this->expectException(\RuntimeException::class);
        $query->select(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '>', 'or');
    }

    public function testOrderBy(): void
    {
        $query = new Query();
        $query->orderBy(['fieldA', 'fieldB' => 'desc', 'fieldC' => 'asc', 'fieldD']);
        $this->assertSame('order by fieldA, fieldB desc, fieldC asc, fieldD', $query->sql);
        $query->clear();
        $query->orderBy(['fieldA']);
        $this->assertSame('order by fieldA', $query->sql);
        $query->clear();
        $query->orderBy(['fieldB' => 'desc']);
        $this->assertSame('order by fieldB desc', $query->sql);
    }

    public function testParens(): void
    {
        $query = new Query();
        $query->select(['1', 'cool'])->append('where')->parens(fn ($q) => $q->append('1 = 2 and 3 = 4'));
        $this->assertSame('select 1, cool where ( 1 = 2 and 3 = 4 )', $query->sql);
    }

    public function testLimit(): void
    {
        $query = new Query();
        $query->select(['*'])->from('things')->limit(50);
        $this->assertSame('select * from things limit ?', $query->sql);
        $this->assertSame([50], $query->params);
    }

    public function testOffset(): void
    {
        $query = new Query();
        $query->select(['*'])->from('things')->offset(10);
        $this->assertSame('select * from things offset ?', $query->sql);
        $this->assertSame([10], $query->params);
    }

    public function testUpdateAndSet(): void
    {
        $query = new Query();
        $this->createThingsTable($query);
        $query->insertInto('things', ['x' => 3, 'y' => 'cool', 'z' => false])->run($this->pdo);
        $query->clear();
        $query->update('things')->set(['x' => 50, 'z' => null]);
        $this->assertSame('update things set x = ?, z = ?', $query->sql);
        $this->assertSame([50, null], $query->params);
        $query->run($this->pdo);
        $query->clear();
        $results = $query->select(['x', 'y', 'z'])->from('things')->fetchAll($this->pdo);
        $this->assertSame(50, $results[0]['x']);
        $this->assertSame('cool', $results[0]['y']);
        $this->assertSame(null, $results[0]['z']);
    }

    public function testDeleteFrom(): void
    {
        $query = new Query();
        $this->createThingsTable($query);
        $query->insertInto('things', ['x' => 3, 'y' => 'cool', 'z' => false])->run($this->pdo);
        $query->clear()->insertInto('things', ['x' => 5, 'y' => 'cool2', 'z' => true])->run($this->pdo);
        $query->clear()->insertInto('things', ['x' => 3, 'y' => 'cool2', 'z' => true])->run($this->pdo);
        $query->clear();
        $query->deleteFrom('things');
        $this->assertSame('delete from things', $query->sql);
        $query->where(['x' => 5]);
        $this->assertSame('delete from things where ( x = ? )', $query->sql);
        $query->run($this->pdo);
        $query->clear();
        $results = $query->select(['x'])->from('things')->fetchAll($this->pdo);
        $this->assertSame(2, count($results));
        $this->assertSame(3, $results[1]['x']);
    }

    public function testReturning(): void
    {
        $query = new Query();
        $this->createThingsTable($query);
        $query->insertInto('things', ['x' => 3, 'y' => 'cool', 'z' => false])->returning(['y', '3']);
        $this->assertSame("insert into things (x, y, z) values (?, ?, ?) returning y, 3", $query->sql);
        // Testing against SQLite so can't execute this one as RETURNING is postgres-specific.
    }

    public function testWith(): void
    {
        $query = new Query();
        $query->with([
            'items' => fn ($q) => $q->select(['x'])->from('things')->where(['y' => 3]),
            'other_items' => fn ($q) => $q->select(['y', 'z'])->from('things')->where(['z' => 50])->orderBy(['z']),
        ])->select(['x'])->from('items');
        $this->assertSame(
            'with items as ( select x from things where ( y = ? ) ), other_items as ( select y, z from ' .
                'things where ( z = ? ) order by z ) select x from items',
            $query->sql,
        );
        $this->assertSame([3, 50], $query->params);
    }

    public function testWhereIn(): void
    {
        $query = new Query();
        $query->select(['x'])->from('things')->where(['y' => [1, 20, 25]], 'in');
        $this->assertSame(
            'select x from things where ( y in ( ?, ?, ? ) )',
            $query->sql,
        );
        $this->assertSame([1, 20, 25], $query->params);
    }

    public function testLiteral(): void
    {
        $query = new Query();
        $query->select(['x'])->from('things')->where(['y' => Query::literal('NOW()')], '>');
        $this->assertSame('select x from things where ( y > NOW() )', $query->sql);
    }

    public function testAppend(): void
    {
        $query = new Query();
        $query->select(['x'])->from('things')->append('then write anything')->append('cool');
        $this->assertSame('select x from things then write anything cool', $query->sql);
        $query->clear();
        $query->append('with hi');
        $this->assertSame('with hi', $query->sql);
    }

    public function testAppendTight(): void
    {
        $query = new Query();
        $query->select(['x'])->from('things')->appendTight('then write anything')->append('word')->appendTight('cool');
        $this->assertSame('select x from thingsthen write anything wordcool', $query->sql);
        $query->clear();
        $query->appendTight('with hi ');
        $this->assertSame('with hi ', $query->sql);
    }

    public function testAppendParamAndParams(): void
    {
        $query = new Query();
        $this->assertSame([], $query->params);
        $query->appendParam(30);
        $this->assertSame([30], $query->params);
        $query->appendParam('hi');
        $this->assertSame([30, 'hi'], $query->params);
    }

    public function testSql(): void
    {
        $query = new Query()->append('words are here');
        $this->assertSame('words are here', $query->sql);
    }
}
