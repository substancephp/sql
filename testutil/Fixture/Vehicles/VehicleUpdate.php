<?php

declare(strict_types=1);

namespace TestUtil\Fixture\Vehicles;

use SubstancePHP\SQL\Noop;
use SubstancePHP\SQL\Update;

/** @extends Update<self> */
class VehicleUpdate extends Update
{
    public function __construct(
        public Noop|string $kind,
        public Noop|string $make,
        public Noop|string $model,
        public Noop|int $year,
        public Noop|string|null $briefDescription,
    ) {
        parent::__construct(new VehicleTableSchema());
    }
}