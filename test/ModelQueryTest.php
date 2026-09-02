<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SubstancePHP\SQL\ModelQuery;
use PHPUnit\Framework\TestCase;
use SubstancePHP\SQL\Query;
use TestUtil\Fixture\Vehicle;

#[CoversClass(ModelQuery::class)]
final class ModelQueryTest extends TestCase
{
    private \PDO $pdo;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->createVehiclesTable();
        parent::setUp();
    }

    private function createVehiclesTable(): void
    {
        new Query()
            ->append('create table vehicles(' .
                'id integer primary key autoincrement, kind, make, model, year, brief_description' .
            ')')
            ->run($this->pdo);
    }

    #[Test]
    public function findAndFetchFirst(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $retrieved = ModelQuery::find(Vehicle::class, 1)->first($this->pdo);
        $this->assertNotNull($retrieved);
        $this->assertSame('Falcon', $retrieved->model);
    }

    #[Test]
    public function aggregates(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $this->assertSame(1, (int) ModelQuery::selectCountFrom(Vehicle::class)->fetchColumn($this->pdo));
        $this->assertSame(
            2000,
            (int) ModelQuery::selectSumFrom(Vehicle::class, 'year')->fetchColumn($this->pdo),
        );
        $this->assertTrue(
            ModelQuery::selectFrom(Vehicle::class)->where(['make' => 'Ford'])->exists($this->pdo),
        );
        $this->assertFalse(
            ModelQuery::selectFrom(Vehicle::class)->where(['make' => 'Honda'])->exists($this->pdo),
        );
    }

    #[Test]
    public function insertSelectUpdateDelete(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Holden';
        $vehicle->model = 'Commodore';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $vehicle = new Vehicle();
        $vehicle->kind = 'bike';
        $vehicle->make = 'Yamaha';
        $vehicle->model = 'Enduro';
        $vehicle->year = 1977;
        $vehicle->briefDescription = 'motorbike';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $results = ModelQuery::selectFrom(Vehicle::class)
            ->where(['year' => 2000])
            ->orderBy(['model'])
            ->fetch($this->pdo);
        $this->assertCount(2, $results);
        $this->assertSame('Holden', $results[0]->make);
        $this->assertSame('Ford', $results[1]->make);
        $this->assertSame(2, $results[0]->id);
        $this->assertSame(1, $results[1]->id);

        $holden = Vehicle::makeDefault();
        $holden->id = 2;
        $holden->year = 1996;
        $holden->model = 'Berina';
        $holden->briefDescription = null;
        ModelQuery::update($holden)->run($this->pdo);

        $results = ModelQuery::selectFrom(Vehicle::class)->where(['id' => 2])->fetch($this->pdo);
        $this->assertCount(1, $results);
        $this->assertNull($results[0]->briefDescription);
        $this->assertSame('Berina', $results[0]->model);
        $this->assertSame(1996, $results[0]->year);
        $this->assertSame('Holden', $results[0]->make);

        ModelQuery::deleteFrom(Vehicle::class)->where(['id' => 2])->run($this->pdo);
        $results = ModelQuery::selectFrom(Vehicle::class)->where(['id' => 2])->fetch($this->pdo);
        $this->assertCount(0, $results);
    }

    #[Test]
    public function insertMany(): void
    {
        $ford = new Vehicle();
        $ford->kind = 'car';
        $ford->make = 'Ford';
        $ford->model = 'Falcon';
        $ford->year = 2000;
        $ford->briefDescription = 'flagship sedan';

        $holden = new Vehicle();
        $holden->kind = 'car';
        $holden->make = 'Holden';
        $holden->model = 'Commodore';
        $holden->year = 2000;
        $holden->briefDescription = 'flagship sedan';

        $yamaha = new Vehicle();
        $yamaha->kind = 'bike';
        $yamaha->make = 'Yamaha';
        $yamaha->model = 'Enduro';
        $yamaha->year = 1977;
        $yamaha->briefDescription = 'motorbike';

        $inserted = ModelQuery::insertMany($this->pdo, [$ford, $holden, $yamaha], 2);
        $this->assertSame(3, $inserted);
        $this->assertSame(3, (int) ModelQuery::selectCountFrom(Vehicle::class)->fetchColumn($this->pdo));
    }

    #[Test]
    public function groupByBuildsAndRuns(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $vehicle = new Vehicle();
        $vehicle->kind = 'bike';
        $vehicle->make = 'Yamaha';
        $vehicle->model = 'Enduro';
        $vehicle->year = 1977;
        $vehicle->briefDescription = 'motorbike';
        ModelQuery::insert($vehicle)->run($this->pdo);

        $grouped = ModelQuery::selectFrom(Vehicle::class)->groupBy(['kind'])->fetch($this->pdo);
        $this->assertCount(2, $grouped);
    }

    #[Test]
    public function updateWithoutPrimaryKeyThrows(): void
    {
        $vehicle = Vehicle::makeDefault();
        $vehicle->kind = 'car';
        $this->expectException(\InvalidArgumentException::class);
        ModelQuery::update($vehicle);
    }

    #[Test]
    public function deleteWithoutPrimaryKeyThrows(): void
    {
        $vehicle = Vehicle::makeDefault();
        $this->expectException(\InvalidArgumentException::class);
        ModelQuery::delete($vehicle);
    }

    #[Test]
    public function updateWithZeroPrimaryKeyBuildsQuery(): void
    {
        $vehicle = Vehicle::makeDefault();
        $vehicle->id = 0;
        $vehicle->kind = 'car';

        $query = ModelQuery::update($vehicle);
        $this->assertSame('update vehicles set kind = ? where ( id = ? )', $query->getSql());
    }
}
