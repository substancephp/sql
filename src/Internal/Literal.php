<?php

declare(strict_types=1);

namespace SubstancePHP\SQL\Internal;

readonly class Literal
{
    public function __construct(private string $str)
    {
    }

    public function __toString(): string
    {
        return $this->str;
    }
}
