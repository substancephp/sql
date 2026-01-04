<?php

declare(strict_types=1);

namespace TestUtil\Fixture\Vehicles;

use SubstancePHP\SQL\Insert;

/** @extends Insert<self> */
class VehicleInsert extends Insert
{
    public function __construct(
        public string $kind,
        public string $make,
        public string $model,
        public int $year,
        public ?string $briefDescription,
    ) {
        parent::__construct(new VehicleTableSchema());
    }
}
