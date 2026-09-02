<?php

declare(strict_types=1);

namespace TestUtil\Fixture;

use SubstancePHP\SQL\Attributes\Column;
use SubstancePHP\SQL\Attributes\Table;
use SubstancePHP\SQL\Model;
use SubstancePHP\SQL\Traits\ActsAsModel;

/** @implements Model<self> */
#[Table('vehicles', 'id')]
class Vehicle implements Model
{
    use ActsAsModel;

    #[Column('id')]
    public int $vehicleId;

    #[Column('kind')]
    public string $kind;

    #[Column('make')]
    public string $make;

    #[Column('model')]
    public string $model;

    #[Column('year')]
    public int $year;

    #[Column('brief_description')]
    public ?string $briefDescription;

    /** @var array<string, mixed> */
    public array $notes = [];
}
