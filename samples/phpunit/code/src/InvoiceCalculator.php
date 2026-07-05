<?php

declare(strict_types=1);

namespace App;

final class InvoiceCalculator
{
    public function totalWithTax(int $subtotal, int $tax): int
    {
        return $subtotal + $tax;
    }
}
