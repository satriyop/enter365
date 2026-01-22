<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Purchasing\PurchaseOrderCalculatorInterface;
use App\Models\Purchasing\PurchaseOrder;

/**
 * Factory for creating purchase order domain objects with proper dependency injection.
 *
 * Centralizes domain object creation for PurchaseOrder, providing a single entry point
 * for state machine access, total calculations, and receiving progress tracking.
 *
 * @example
 * // In a service:
 * public function __construct(private PurchaseOrderDomainFactory $domainFactory) {}
 *
 * public function submitPO(PurchaseOrder $po): PurchaseOrder
 * {
 *     $stateMachine = $this->domainFactory->stateMachine($po);
 *     if (!$stateMachine->canSubmit()) {
 *         throw new InvalidArgumentException('Cannot submit PO');
 *     }
 *     $stateMachine->transitionTo(DocumentStatus::Submitted);
 * }
 */
class PurchaseOrderDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private PurchaseOrderCalculatorInterface $calculator,
    ) {}

    /**
     * Create a state machine for the given purchase order.
     *
     * The state machine is created fresh each time since it holds
     * purchase order-specific state.
     */
    public function stateMachine(PurchaseOrder $purchaseOrder): PurchaseOrderStateMachine
    {
        return PurchaseOrderStateMachine::fromPurchaseOrder($purchaseOrder, $this->eventDispatcher);
    }

    /**
     * Calculate totals for a purchase order.
     *
     * @return PurchaseOrderTotals The calculated totals DTO
     */
    public function calculateTotals(PurchaseOrder $purchaseOrder): PurchaseOrderTotals
    {
        $lineTotals = $purchaseOrder->items->pluck('line_total')->toArray();

        return $this->calculator->calculate(
            $lineTotals,
            (float) ($purchaseOrder->tax_rate ?? 0),
            $purchaseOrder->discount_type,
            (float) ($purchaseOrder->discount_value ?? 0),
            $purchaseOrder->currency ?? 'IDR',
            (float) ($purchaseOrder->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to a purchase order (without saving).
     *
     * Updates the purchase order's financial fields based on line items.
     */
    public function applyTotals(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $totals = $this->calculateTotals($purchaseOrder);

        $purchaseOrder->subtotal = $totals->subtotal;
        $purchaseOrder->discount_amount = $totals->discountAmount;
        $purchaseOrder->tax_amount = $totals->taxAmount;
        $purchaseOrder->total_amount = $totals->totalAmount;
        $purchaseOrder->base_currency_total = $totals->baseCurrencyTotal;

        return $purchaseOrder;
    }

    /**
     * Get unreceived items for a purchase order.
     *
     * Returns items that have not been fully received, useful for
     * GRN (Goods Receipt Note) creation.
     *
     * @return array<int, array{item_id: int, product_id: int|null, description: string, ordered: float, received: float, unreceived: float}>
     */
    public function getUnreceivedItems(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->items
            ->filter(fn ($item) => $item->quantity > $item->quantity_received)
            ->map(fn ($item) => [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'ordered' => (float) $item->quantity,
                'received' => (float) $item->quantity_received,
                'unreceived' => (float) $item->quantity - (float) $item->quantity_received,
            ])
            ->values()
            ->all();
    }

    /**
     * Get receiving progress percentage.
     */
    public function getReceivingProgress(PurchaseOrder $purchaseOrder): float
    {
        $totalQty = $purchaseOrder->items->sum('quantity');
        $receivedQty = $purchaseOrder->items->sum('quantity_received');

        if ($totalQty == 0) {
            return 0.0;
        }

        return round(($receivedQty / $totalQty) * 100, 2);
    }

    /**
     * Check if purchase order is fully received.
     */
    public function isFullyReceived(PurchaseOrder $purchaseOrder): bool
    {
        foreach ($purchaseOrder->items as $item) {
            if ($item->quantity_received < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if purchase order has any received items.
     */
    public function hasReceivedItems(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->items->where('quantity_received', '>', 0)->isNotEmpty();
    }

    /**
     * Get the full PO number with revision suffix.
     */
    public function getFullNumber(PurchaseOrder $purchaseOrder): string
    {
        if ($purchaseOrder->revision > 0) {
            return "{$purchaseOrder->po_number}-R{$purchaseOrder->revision}";
        }

        return $purchaseOrder->po_number;
    }

    /**
     * Check if PO is overdue for receiving.
     *
     * Returns true if expected_date has passed and not fully received.
     */
    public function isOverdueForReceiving(PurchaseOrder $purchaseOrder): bool
    {
        $expectedDate = $purchaseOrder->expected_date;
        $status = $purchaseOrder->status;
        $statusValue = $status instanceof \App\Enums\DocumentStatus ? $status->value : (string) $status;

        return $expectedDate instanceof \Carbon\Carbon
            && $expectedDate->isPast()
            && ! $this->isFullyReceived($purchaseOrder)
            && in_array($statusValue, ['approved', 'partial'], true);
    }

    /**
     * Get days until expected delivery (negative if overdue).
     */
    public function getDaysUntilExpected(PurchaseOrder $purchaseOrder): int
    {
        $expectedDate = $purchaseOrder->expected_date;

        if (! $expectedDate instanceof \Carbon\Carbon) {
            return 0;
        }

        return (int) now()->diffInDays($expectedDate, false);
    }
}
