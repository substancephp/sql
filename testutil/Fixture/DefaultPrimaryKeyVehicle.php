<?php

declare(strict_types=1);

namespace TestUtil\Fixture;

use SubstancePHP\SQL\Attributes\Column;
use SubstancePHP\SQL\Attributes\Table;
use SubstancePHP\SQL\Model;
use SubstancePHP\SQL\Traits\ActsAsModel;

/** @implements Model<self> */
#[Table('default_pk_vehicles')]
class DefaultPrimaryKeyVehicle implements Model
{
    use ActsAsModel;

    #[Column('id')]
    public int $id;

    #[Column('kind')]
    public string $kind;
}
