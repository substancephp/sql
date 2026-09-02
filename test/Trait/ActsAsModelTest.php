<?php

declare(strict_types=1);

namespace Test\Trait;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SubstancePHP\SQL\ModelQuery;
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
        $this->assertNotNull($retrieved);
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
        $this->assertSame(2, $results[0]->vehicleId);
        $this->assertSame(1, $results[1]->vehicleId);

        $holden = Vehicle::makeDefault();
        $holden->vehicleId = 2;
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
        $this->assertNotNull($vehicle);
        $vehicle->delete()->run($this->pdo);
        $results = Vehicle::selectAll()->where(['id' => 2])->fetch($this->pdo);
        $this->assertCount(0, $results);

        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Honda';
        $vehicle->model = 'Civic';
        $vehicle->year = 1980;
        $vehicle->briefDescription = null;
        $this->assertFalse($vehicle->propertyIsInitialized('vehicleId'));
        $vehicle->save()->run($this->pdo);
        $vehicle = Vehicle::selectAll()->where(['model' => 'Civic'])->first($this->pdo);
        $this->assertNotNull($vehicle);
        $this->assertSame('Civic', $vehicle->model);
        $this->assertGreaterThan(0, $vehicle->vehicleId);
    }

    #[Test]
    public function primaryKeyPropertyIsDistinguishedFromColumn(): void
    {
        $this->assertSame('id', Vehicle::getPrimaryKeyColumn());
        $this->assertSame('vehicleId', Vehicle::getPrimaryKeyProperty());
    }

    #[Test]
    public function writeableValuesIncludeOnlyInitializedProperties(): void
    {
        $vehicle = new Vehicle();
        $vehicle->make = 'Ford';
        $vehicle->briefDescription = null;

        $this->assertSame(
            ['make' => 'Ford', 'brief_description' => null],
            $vehicle->getWriteableValues(),
        );
    }

    #[Test]
    public function readingUninitializedPropertyThrows(): void
    {
        $vehicle = new Vehicle();

        $this->expectException(\Error::class);
        $this->assertSame('', $vehicle->kind);
    }

    #[Test]
    public function readingInitializedProperties(): void
    {
        $vehicle = new Vehicle();
        $vehicle->briefDescription = null;
        $vehicle->year = 2000;

        $this->assertNull($vehicle->briefDescription);
        $this->assertSame(2000, $vehicle->year);
    }

    #[Test]
    public function propertyInitializationIsDistinguishedFromNull(): void
    {
        $vehicle = new Vehicle();
        $this->assertFalse($vehicle->propertyIsInitialized('briefDescription'));

        $vehicle->briefDescription = null;
        $this->assertTrue($vehicle->propertyIsInitialized('briefDescription'));
    }

    #[Test]
    public function hydrationInitialisesOnlyPresentColumns(): void
    {
        $vehicle = new Vehicle();
        $vehicle->readFromQueryResult(['make' => 'Ford']);

        $this->assertTrue($vehicle->propertyIsInitialized('make'));
        $this->assertFalse($vehicle->propertyIsInitialized('kind'));

        $this->expectException(\Error::class);
        $this->assertSame('', $vehicle->kind);
    }

    #[Test]
    public function hydrationInitialisesNullColumns(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = null;
        $vehicle->insert()->run($this->pdo);

        $retrieved = Vehicle::find(1)->first($this->pdo);
        $this->assertNotNull($retrieved);
        $this->assertTrue($retrieved->propertyIsInitialized('briefDescription'));
        $this->assertNull($retrieved->briefDescription);
    }

    #[Test]
    public function saveUpdatesWhenPrimaryKeyInitialized(): void
    {
        $vehicle = new Vehicle();
        $vehicle->kind = 'car';
        $vehicle->make = 'Ford';
        $vehicle->model = 'Falcon';
        $vehicle->year = 2000;
        $vehicle->briefDescription = 'flagship sedan';
        $vehicle->insert()->run($this->pdo);

        $retrieved = Vehicle::find(1)->first($this->pdo);
        $this->assertNotNull($retrieved);
        $retrieved->year = 1999;
        $retrieved->save()->run($this->pdo);

        $again = Vehicle::find(1)->first($this->pdo);
        $this->assertNotNull($again);
        $this->assertSame(1999, $again->year);
    }

    #[Test]
    public function inMemoryOnlyPropertyIsIgnoredByWrites(): void
    {
        $vehicle = new Vehicle();
        $vehicle->make = 'Ford';
        $vehicle->notes = ['x' => 'y'];

        $this->assertSame(['make' => 'Ford'], $vehicle->getWriteableValues());
        $this->assertSame(['x' => 'y'], $vehicle->notes);
        $this->assertNotContains('notes', $vehicle->getColumns());
    }
}
