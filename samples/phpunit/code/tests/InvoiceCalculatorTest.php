<?php

declare(strict_types=1);

namespace Tests;

use App\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

final class InvoiceCalculatorTest extends TestCase
{
    public function test_adds_tax_to_subtotal(): void
    {
        self::assertSame(125, (new InvoiceCalculator)->totalWithTax(100, 25));
    }
}
