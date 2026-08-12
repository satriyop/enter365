<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Domain\Sales\Invoices\InvoiceTotals;
use App\Models\Purchasing\PurchaseReturn;

/**
 * Factory for PurchaseReturn domain objects (state machine access + totals).
 */
class PurchaseReturnDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private InvoiceCalculatorInterface $calculator,
    ) {}

    public function stateMachine(PurchaseReturn $purchaseReturn): PurchaseReturnStateMachine
    {
        return PurchaseReturnStateMachine::fromPurchaseReturn($purchaseReturn, $this->eventDispatcher);
    }

    public function calculateTotals(PurchaseReturn $purchaseReturn): InvoiceTotals
    {
        $lineTotals = $purchaseReturn->items->pluck('line_total')->toArray();
        $taxRate = (float) ($purchaseReturn->tax_rate ?? config('accounting.tax.default_rate', 11.00));

        return $this->calculator->calculate(
            $lineTotals,
            $taxRate,
            0,
            $purchaseReturn->currency ?? 'IDR',
            (float) ($purchaseReturn->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to a purchase return (without saving).
     */
    public function applyTotals(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        $totals = $this->calculateTotals($purchaseReturn);

        $purchaseReturn->subtotal = $totals->subtotal;
        $purchaseReturn->tax_amount = $totals->taxAmount;
        $purchaseReturn->total_amount = $totals->totalAmount;

        return $purchaseReturn;
    }
}
