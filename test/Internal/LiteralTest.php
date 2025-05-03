<?php

declare(strict_types=1);

namespace Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SubstancePHP\SQL\Internal\Literal;

#[CoversClass(Literal::class)]
class LiteralTest extends TestCase
{
    #[Test]
    public function constructAndString(): void
    {
        $literal = new Literal('hi');
        $str = (string) $literal;
        $this->assertEquals('hi', $str);
    }
}
