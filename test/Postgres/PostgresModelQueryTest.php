<?php

declare(strict_types=1);

namespace Test\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SubstancePHP\SQL\ModelQuery;
use TestUtil\Fixture\Vehicle;
use TestUtil\PostgresTestCase;

#[CoversClass(ModelQuery::class)]
final class PostgresModelQueryTest extends PostgresTestCase
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
    public function modelPropertiesPopulateFromQuotedAliases(): void
    {
        $this->pdo()->exec(
            'insert into vehicles (kind, make, model, year, brief_description) values' .
                "('car', 'Ford', 'Falcon', 2000, 'flagship sedan')",
        );

        $vehicle = ModelQuery::selectFrom(Vehicle::class)
            ->where(['make' => 'Ford'])
            ->first($this->pdo());

        $this->assertNotNull($vehicle);
        $this->assertSame('flagship sedan', $vehicle->briefDescription);
        $this->assertSame(2000, $vehicle->year);
    }
}
