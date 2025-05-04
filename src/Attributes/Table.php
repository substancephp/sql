<?php

declare(strict_types=1);

namespace SubstancePHP\SQL\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(public string $name, public string $primaryKey)
    {
    }
}
