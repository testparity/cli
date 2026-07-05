<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\InvoiceTotal;
use PHPUnit\Framework\TestCase;

final class InvoiceTotalTest extends TestCase
{
    public function test_adds_tax_to_subtotal(): void
    {
        self::assertSame(125, (new InvoiceTotal)->fromSubtotal(100, 25));
    }
}
