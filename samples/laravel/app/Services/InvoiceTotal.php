<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceTotal
{
    public function fromSubtotal(int $subtotal, int $tax): int
    {
        return $subtotal + $tax;
    }
}
