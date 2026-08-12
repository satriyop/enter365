<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Domain\Sales\Invoices\InvoiceTotals;
use App\Models\Purchasing\Bill;

/**
 * Factory for Bill domain objects (state machine access + totals).
 */
class BillDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private InvoiceCalculatorInterface $calculator,
    ) {}

    public function stateMachine(Bill $bill): BillStateMachine
    {
        return BillStateMachine::fromBill($bill, $this->eventDispatcher);
    }

    public function calculateTotals(Bill $bill): InvoiceTotals
    {
        $lineTotals = $bill->items->pluck('line_total')->toArray();

        return $this->calculator->calculate(
            $lineTotals,
            (float) ($bill->tax_rate ?? 0),
            (int) ($bill->discount_amount ?? 0),
            $bill->currency ?? 'IDR',
            (float) ($bill->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to a bill (without saving).
     */
    public function applyTotals(Bill $bill): Bill
    {
        $totals = $this->calculateTotals($bill);

        $bill->subtotal = $totals->subtotal;
        $bill->tax_amount = $totals->taxAmount;
        $bill->total_amount = $totals->totalAmount;

        $exchangeRate = (float) ($bill->exchange_rate ?? 1);
        if (($bill->currency ?? 'IDR') !== 'IDR' && $exchangeRate > 0) {
            $bill->base_currency_total = (int) round($bill->total_amount * $exchangeRate);
        } else {
            $bill->base_currency_total = $bill->total_amount;
        }

        return $bill;
    }
}
