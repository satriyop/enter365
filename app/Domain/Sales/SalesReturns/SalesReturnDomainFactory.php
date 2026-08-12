<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Domain\Sales\Invoices\InvoiceTotals;
use App\Models\Sales\SalesReturn;

/**
 * Factory for SalesReturn domain objects (state machine access + totals).
 */
class SalesReturnDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private InvoiceCalculatorInterface $calculator,
    ) {}

    public function stateMachine(SalesReturn $salesReturn): SalesReturnStateMachine
    {
        return SalesReturnStateMachine::fromSalesReturn($salesReturn, $this->eventDispatcher);
    }

    public function calculateTotals(SalesReturn $salesReturn): InvoiceTotals
    {
        $lineTotals = $salesReturn->items->pluck('line_total')->toArray();
        $taxRate = (float) ($salesReturn->tax_rate ?? config('accounting.tax.default_rate', 11.00));

        return $this->calculator->calculate(
            $lineTotals,
            $taxRate,
            0,
            $salesReturn->currency ?? 'IDR',
            (float) ($salesReturn->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to a sales return (without saving).
     */
    public function applyTotals(SalesReturn $salesReturn): SalesReturn
    {
        $totals = $this->calculateTotals($salesReturn);

        $salesReturn->subtotal = $totals->subtotal;
        $salesReturn->tax_amount = $totals->taxAmount;
        $salesReturn->total_amount = $totals->totalAmount;

        return $salesReturn;
    }
}
