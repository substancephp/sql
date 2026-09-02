<?php

declare(strict_types=1);

namespace TestUtil\Fixture;

use SubstancePHP\SQL\Attributes\Column;
use SubstancePHP\SQL\Attributes\Table;
use SubstancePHP\SQL\Model;
use SubstancePHP\SQL\Traits\ActsAsModel;

/** @implements Model<self> */
#[Table('primary_keyless_vehicles')]
class PrimaryKeylessVehicle implements Model
{
    use ActsAsModel;

    public static function getPrimaryKeyProperty(): ?string
    {
        return null;
    }

    #[Column('kind')]
    public string $kind;

    #[Column('make')]
    public string $make;
}
