<?php

declare(strict_types=1);

namespace Test\Trait;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SubstancePHP\SQL\ModelQuery;
use SubstancePHP\SQL\Noop;
use SubstancePHP\SQL\Query;
use TestUtil\Fixture\Vehicle;

#[CoversClass(ModelQuery::class)]
final class ActsAsModelTest extends TestCase
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
    public function insertAndFindFirst(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        $vehicle->insert()->run($this->pdo);

        $retrieved = Vehicle::find(1)->first($this->pdo);
        $this->assertSame('Falcon', $retrieved->model);
    }

    #[Test]
    public function insertSelectUpdateSaveDelete(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        $vehicle->insert()->run($this->pdo);

        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Holden';
        $vehicle->model = 'Commodore';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        $vehicle->insert()->run($this->pdo);

        $vehicle = new Vehicle();
        $vehicle->kind = 'bike';
        $vehicle->make = 'Yamaha';
        $vehicle->model = 'Enduro';
        $vehicle->year = 1977;
        $vehicle->briefDescription = 'motorbike';
        $vehicle->insert()->run($this->pdo);

        $results = Vehicle::selectAll()
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
        $holden->update()->run($this->pdo);

        $results = Vehicle::selectAll()->where(['id' => 2])->fetch($this->pdo);
        $this->assertCount(1, $results);
        $this->assertNull($results[0]->briefDescription);
        $this->assertSame('Berina', $results[0]->model);
        $this->assertSame(1996, $results[0]->year);
        $this->assertSame('Holden', $results[0]->make);

        $vehicle = Vehicle::find(2)->first($this->pdo);
        $vehicle->delete()->run($this->pdo);
        $results = Vehicle::selectAll()->where(['id' => 2])->fetch($this->pdo);
        $this->assertCount(0, $results);

        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Honda';
        $vehicle->model = 'Civic';
        $vehicle->year = 1980;
        $vehicle->briefDescription = null;
        $this->assertSame(Noop::T, $vehicle->id);
        define('TRY_IT', true);
        $vehicle->save()->run($this->pdo);
        $vehicle = Vehicle::selectAll()->where(['model' => 'Civic'])->first($this->pdo);
        $this->assertSame('Civic', $vehicle->model);
        $this->assertIsInt($vehicle->id);
    }
}
