<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SubstancePHP\SQL\Query;
use PHPUnit\Framework\TestCase;
use TestUtil\Fixture\Vehicle;

#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
    private \PDO $pdo;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        parent::setUp();
    }

    private function createThingsTable(): void
    {
        $query = new Query();
        $query->append('create table things (x, y, z, t)')->run($this->pdo);
    }

    private function createVehiclesTable(): void
    {
        $query = new Query();
        $query->append('create table vehicles (kind, make, model, year, brief_description)')->run($this->pdo);
    }

    #[Test]
    public function runAndClear(): void
    {
        $this->createThingsTable();
        new Query()->append('insert into things (x, y, z) values (10, 20, 30)')->run($this->pdo);
        $statement = new Query()->append('select * from things')->run($this->pdo);
        $results = $statement->fetchAll();
        $this->assertSame(
            [['x' => 10, 0 => 10, 'y' => 20, 1 => 20, 'z' => 30, 2 => 30, 't' => null, 3 => null]],
            $results,
        );
    }

    #[Test]
    public function fetchAll(): void
    {
        $query = new Query();
        $query->append('select 1 as x');
        $results = $query->fetchAll($this->pdo);
        $this->assertSame(1, $results[0]['x']);
    }

    #[Test]
    public function fetchModels(): void
    {
        $this->createVehiclesTable();
        Query::insertInto('vehicles', [
            'kind' => 'car',
            'make' => 'Toyota',
            'model' => 'Crown',
            'year' => 1990,
            'brief_description' => 'flagship luxury sedan',
        ])->run($this->pdo);
        Query::insertInto('vehicles', [
            'kind' => 'car',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 1999,
            'brief_description' => 'family sedan',
        ])->run($this->pdo);

        $vehicles = Query::select([
            'kind',
            'make',
            'model',
            'year',
            'briefDescription' => 'brief_description',
        ])->from('vehicles')->fetchModels($this->pdo, Vehicle::class);

        $this->assertCount(2, $vehicles);

        $this->assertInstanceOf(Vehicle::class, $vehicles[0]);
        $this->assertEquals('car', $vehicles[0]->kind);
        $this->assertEquals('Toyota', $vehicles[0]->make);
        $this->assertEquals('Crown', $vehicles[0]->model);
        $this->assertEquals(1990, $vehicles[0]->year);
        $this->assertEquals('flagship luxury sedan', $vehicles[0]->briefDescription);

        $this->assertInstanceOf(Vehicle::class, $vehicles[1]);
        $this->assertEquals('car', $vehicles[1]->kind);
        $this->assertEquals('Toyota', $vehicles[1]->make);
        $this->assertEquals('Camry', $vehicles[1]->model);
        $this->assertEquals(1999, $vehicles[1]->year);
        $this->assertEquals('family sedan', $vehicles[1]->briefDescription);
    }

    #[Test]
    public function fetchColumn(): void
    {
        $query = new Query();
        $query->append('select 1 as x');
        $result = $query->fetchColumn($this->pdo);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function insertSelectFrom(): void
    {
        $this->createThingsTable();
        $query = Query::insertInto('things', [
            'x' => 'hi',
            'y' => 30,
            'z' => null,
            't' => Query::literal("date('now')"),
        ]);
        $query->run($this->pdo);
        $this->assertSame("insert into things (x, y, z, t) values (?, ?, ?, date('now'))", $query->sql);
        Query::insertInto('things', ['x' => 'nice', 'y' => 2, 'z' => 5.6])->run($this->pdo);
        $query = Query::select(['x', 'y', 'stuff' => 'z'])->from('things');
        $this->assertSame('select x, y, z as stuff from things', $query->sql);
        $results = $query->fetchAll($this->pdo);
        $this->assertSame(2, count($results));
        $this->assertSame(2, $results[1]['y']);
        $this->assertSame(null, $results[0]['stuff']);
        $this->assertSame('nice', $results[1]['x']);
    }

    #[Test]
    public function innerJoinOn(): void
    {
        $query = Query::select(['x', 'y', 'z'])
            ->from('items')->innerJoin('cool_things')->on('whatever');
        $this->assertSame('select x, y, z from items inner join cool_things on (whatever)', $query->sql);
    }

    #[Test]
    public function leftJoinOn(): void
    {
        $query = Query::select(['x', 'y', 'z'])
            ->from('items')->leftJoin('cool_things')
            ->on('whatever = hey.yo');
        $this->assertSame('select x, y, z from items left join cool_things on (whatever = hey.yo)', $query->sql);
    }

    #[Test]
    public function groupBy(): void
    {
        $query = new Query();
        $query->groupBy(['x']);
        $this->assertSame('group by x', $query->sql);
        $query = new Query()->groupBy(['y', 'z', 'africa']);
        $this->assertSame('group by y, z, africa', $query->sql);
    }

    #[Test]
    public function whereAndCo(): void
    {
        // basic case
        $query = new Query();
        $query->appendSelect(['x', 'y'])->from('things')->where(['x' => 3, 'y' => 'cool']);
        $this->assertSame('select x, y from things where ( x = ? and y = ? )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);

        // with different comparator
        $query = new Query();
        $query->appendSelect(['x', 'y'])->from('things')->where(['x' => 3, 'y' => 'cool'], '<=');
        $this->assertSame('select x, y from things where ( x <= ? and y <= ? )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);

        // with different boolean operator, and throwing NULL into the mix
        $query = new Query();
        $query->appendSelect(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '=', 'or');
        $this->assertSame('select x, y from things where ( x = ? or y = ? or z is null )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);

        // with NOT NULL (variant A)
        $query = new Query();
        $query->appendSelect(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '<>', 'or');
        $this->assertSame('select x, y from things where ( x <> ? or y <> ? or z is not null )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $this->assertFalse(isset($query->params[2]));

        // with NOT NULL (variant B)
        $query = Query::select(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '!=', 'or');
        $this->assertSame('select x, y from things where ( x != ? or y != ? or z is not null )', $query->sql);
        $this->assertSame(3, $query->params[0]);
        $this->assertSame('cool', $query->params[1]);
        $this->assertFalse(isset($query->params[2]));

        // combine with andWhere
        $query = Query::select(['x', 'y'])->from('things')
            ->whereNot(['x' => false, 'z' => null])
            ->andWhere(['fun' => 'great', 'things' => false], '>', 'or')
            ->andWhereNot(['x' => 3]);
        $this->assertSame('select x, y from things where not ( x = ? and z is null ) and ' .
            '( fun > ? or things > ? ) and not ( x = ? )', $query->sql);

        // with NOT NULL (invalid comparison)
        $query = new Query();
        $this->expectException(\RuntimeException::class);
        $query->appendSelect(['x', 'y'])->from('things')
            ->where(['x' => 3, 'y' => 'cool', 'z' => null], '>', 'or');
    }

    #[Test]
    public function orderBy(): void
    {
        $query = new Query();
        $query->orderBy(['fieldA', 'fieldB' => 'desc', 'fieldC' => 'asc', 'fieldD']);
        $this->assertSame('order by fieldA, fieldB desc, fieldC asc, fieldD', $query->sql);

        $query = new Query();
        $query->orderBy(['fieldA']);
        $this->assertSame('order by fieldA', $query->sql);

        $query = new Query();
        $query->orderBy(['fieldB' => 'desc']);
        $this->assertSame('order by fieldB desc', $query->sql);
    }

    #[Test]
    public function parens(): void
    {
        $query = Query::select(['1', 'cool'])->append('where')->parens(fn ($q) => $q->append('1 = 2 and 3 = 4'));
        $this->assertSame('select 1, cool where ( 1 = 2 and 3 = 4 )', $query->sql);
    }

    #[Test]
    public function limit(): void
    {
        $query = Query::select(['*'])->from('things')->limit(50);
        $this->assertSame('select * from things limit ?', $query->sql);
        $this->assertSame([50], $query->params);
    }

    #[Test]
    public function offset(): void
    {
        $query = Query::select(['*'])->from('things')->offset(10);
        $this->assertSame('select * from things offset ?', $query->sql);
        $this->assertSame([10], $query->params);
    }

    #[Test]
    public function updateAndSet(): void
    {
        $this->createThingsTable();
        Query::insertInto('things', ['x' => 3, 'y' => 'cool', 'z' => false])->run($this->pdo);
        $query = Query::update('things')->set(['x' => 50, 'z' => null]);
        $this->assertSame('update things set x = ?, z = ?', $query->sql);
        $this->assertSame([50, null], $query->params);
        $query->run($this->pdo);
        $results = Query::select(['x', 'y', 'z'])->from('things')->fetchAll($this->pdo);
        $this->assertSame(50, $results[0]['x']);
        $this->assertSame('cool', $results[0]['y']);
        $this->assertSame(null, $results[0]['z']);
    }

    #[Test]
    public function deleteFrom(): void
    {
        $this->createThingsTable();
        Query::insertInto('things', ['x' => 3, 'y' => 'cool', 'z' => false])->run($this->pdo);
        Query::insertInto('things', ['x' => 5, 'y' => 'cool2', 'z' => true])->run($this->pdo);
        Query::insertInto('things', ['x' => 3, 'y' => 'cool2', 'z' => true])->run($this->pdo);
        $query = Query::deleteFrom('things');
        $this->assertSame('delete from things', $query->sql);
        $query->where(['x' => 5]);
        $this->assertSame('delete from things where ( x = ? )', $query->sql);
        $query->run($this->pdo);
        $results = Query::select(['x'])->from('things')->fetchAll($this->pdo);
        $this->assertSame(2, count($results));
        $this->assertSame(3, $results[1]['x']);
    }

    #[Test]
    public function returning(): void
    {
        $this->createThingsTable();
        $query = Query::insertInto('things', ['x' => 3, 'y' => 'cool', 'z' => false])->returning(['y', '3']);
        $this->assertSame("insert into things (x, y, z) values (?, ?, ?) returning y, 3", $query->sql);
        // Testing against SQLite so can't execute this one as RETURNING is postgres-specific.
    }

    #[Test]
    public function with(): void
    {
        $query = Query::with([
            'items' => fn ($q) => $q->appendSelect(['x'])->from('things')->where(['y' => 3]),
            'other_items' => fn ($q) =>
                $q->appendSelect(['y', 'z'])->from('things')->where(['z' => 50])->orderBy(['z']),
        ])->appendSelect(['x'])->from('items');
        $this->assertSame(
            'with items as ( select x from things where ( y = ? ) ), other_items as ( select y, z from ' .
                'things where ( z = ? ) order by z ) select x from items',
            $query->sql,
        );
        $this->assertSame([3, 50], $query->params);
    }

    #[Test]
    public function whereIn(): void
    {
        $query = Query::select(['x'])->from('things')->where(['y' => [1, 20, 25]], 'in');
        $this->assertSame(
            'select x from things where ( y in ( ?, ?, ? ) )',
            $query->sql,
        );
        $this->assertSame([1, 20, 25], $query->params);
    }

    #[Test]
    public function literal(): void
    {
        $query = Query::select(['x'])->from('things')->where(['y' => Query::literal('NOW()')], '>');
        $this->assertSame('select x from things where ( y > NOW() )', $query->sql);
    }

    #[Test]
    public function append(): void
    {
        $query = Query::select(['x'])->from('things')->append('then write anything')->append('cool');
        $this->assertSame('select x from things then write anything cool', $query->sql);
        $query = new Query()->append('with hi');
        $this->assertSame('with hi', $query->sql);
    }

    #[Test]
    public function appendTight(): void
    {
        $query = Query::select(['x'])
            ->from('things')
            ->appendTight('then write anything')
            ->append('word')
            ->appendTight('cool');
        $this->assertSame('select x from thingsthen write anything wordcool', $query->sql);
        $query = new Query();
        $query->appendTight('with hi ');
        $this->assertSame('with hi ', $query->sql);
    }

    #[Test]
    public function appendParamAndParams(): void
    {
        $query = new Query();
        $this->assertSame([], $query->params);
        $query->appendParam(30);
        $this->assertSame([30], $query->params);
        $query->appendParam('hi');
        $this->assertSame([30, 'hi'], $query->params);
    }
}
