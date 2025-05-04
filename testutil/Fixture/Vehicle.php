<?php

declare(strict_types=1);

namespace TestUtil\Fixture;

use SubstancePHP\SQL\Attributes\Column;
use SubstancePHP\SQL\Attributes\Table;
use SubstancePHP\SQL\Model;
use SubstancePHP\SQL\Noop;
use SubstancePHP\SQL\Traits\ActsAsModel;

/**
 * @implements Model<self>
 */
#[Table('vehicles', 'id')]
class Vehicle implements Model
{
    use ActsAsModel;

    #[Column('id')]
    public Noop|int $id = Noop::T;

    #[Column('kind')]
    public Noop|string $kind = Noop::T;

    #[Column('make')]
    public Noop|string $make = Noop::T;

    #[Column('model')]
    public Noop|string $model = Noop::T;

    #[Column('year')]
    public Noop|int $year = Noop::T;

    #[Column('brief_description')]
    public Noop|string|null $briefDescription = Noop::T;
}
