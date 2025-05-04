<?php

declare(strict_types=1);

namespace SubstancePHP\SQL\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(public string $name)
    {
    }
}
