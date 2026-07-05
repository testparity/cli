<?php

declare(strict_types=1);

namespace Tests;

use App\Calculator;
use PHPUnit\Framework\TestCase;

final class CalculatorTest extends TestCase
{
    public function test_adds_numbers(): void
    {
        self::assertSame(4, (new Calculator)->add(2, 2));
    }
}
