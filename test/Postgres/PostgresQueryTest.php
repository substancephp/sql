<?php

declare(strict_types=1);

namespace Test\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SubstancePHP\SQL\ModelQuery;
use SubstancePHP\SQL\Query;
use TestUtil\Fixture\Vehicle;
use TestUtil\PostgresTestCase;

#[CoversClass(Query::class)]
#[CoversClass(ModelQuery::class)]
final class PostgresQueryTest extends PostgresTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo()->exec(
            'create table if not exists vehicles(' .
                'id serial primary key, kind varchar(255), make varchar(255), model varchar(255),' .
                'year integer, brief_description varchar(255)' .
            ')',
        );
    }

    #[Test]
    public function bulkInsertAndAggregates(): void
    {
        $inserted = Query::insertRows(
            $this->pdo(),
            'vehicles',
            ['kind', 'make', 'model', 'year', 'brief_description'],
            [
                [
                    'kind' => 'car',
                    'make' => 'Ford',
                    'model' => 'Falcon',
                    'year' => 2000,
                    'brief_description' => 'sedan',
                ],
                [
                    'kind' => 'car',
                    'make' => 'Holden',
                    'model' => 'Commodore',
                    'year' => 2000,
                    'brief_description' => 'sedan',
                ],
                [
                    'kind' => 'bike',
                    'make' => 'Yamaha',
                    'model' => 'Enduro',
                    'year' => 1977,
                    'brief_description' => 'motorbike',
                ],
            ],
            2,
        );

        $this->assertSame(3, $inserted);
        $this->assertSame(3, (int) ModelQuery::selectCountFrom(Vehicle::class)->fetchColumn($this->pdo()));
        $this->assertSame(
            2000,
            (int) ModelQuery::selectSumFrom(Vehicle::class, 'year')
                ->where(['year' => 2000])
                ->fetchColumn($this->pdo()),
        );
        $this->assertTrue(
            ModelQuery::selectFrom(Vehicle::class)->where(['make' => 'Holden'])->exists($this->pdo()),
        );
        $this->assertFalse(
            ModelQuery::selectFrom(Vehicle::class)->where(['make' => 'Nissan'])->exists($this->pdo()),
        );
    }

    #[Test]
    public function unpopulatedPrimaryKeyIsRejected(): void
    {
        $vehicle = Vehicle::makeDefault();
        $vehicle->kind = 'car';

        $this->expectException(\InvalidArgumentException::class);
        ModelQuery::update($vehicle);
    }
}
