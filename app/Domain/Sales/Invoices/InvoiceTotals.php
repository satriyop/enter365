<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

readonly class InvoiceTotals
{
    public function __construct(
        public int $subtotal,
        public int $taxAmount,
        public int $totalAmount
    ) {}
}
