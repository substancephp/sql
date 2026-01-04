<?php

declare(strict_types=1);

namespace TestUtil\Fixture\Vehicles;

use SubstancePHP\SQL\Query;

class Vehicle
{
    public function __construct(
        public int $id,
        public string $kind,
        public string $make,
        public string $model,
        public int $year,
        public ?string $briefDescription,
    ) {
    }
}