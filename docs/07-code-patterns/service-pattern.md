---
pattern: service
title: "Service Pattern"
location: app/Services/Accounting/
tags: [architecture, services]
---

# Service Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Creating new business logic
- Implementing CRUD operations
- Handling complex workflows
- Coordinating multiple models

**Key rule:** All business logic lives in services, not controllers or models.

---

## Service Structure

```php
<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private JournalEntryService $journalService,
        private InventoryService $inventoryService
    ) {}

    /**
     * Create a new invoice with items.
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'contact_id' => $data['contact_id'],
                'date' => $data['date'],
                'due_date' => $data['due_date'],
                'number' => $this->generateNumber(),
                'status' => 'draft',
            ]);

            $this->createItems($invoice, $data['items']);
            $this->calculateTotals($invoice);

            return $invoice->fresh(['items', 'contact']);
        });
    }

    /**
     * Update an existing invoice.
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        $this->ensureCanEdit($invoice);

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'contact_id' => $data['contact_id'] ?? $invoice->contact_id,
                'date' => $data['date'] ?? $invoice->date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
            ]);

            if (isset($data['items'])) {
                $invoice->items()->delete();
                $this->createItems($invoice, $data['items']);
            }

            $this->calculateTotals($invoice);

            return $invoice->fresh(['items', 'contact']);
        });
    }

    /**
     * Approve an invoice and create journal entry.
     */
    public function approve(Invoice $invoice): Invoice
    {
        $this->ensureCanApprove($invoice);

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            $this->createJournalEntry($invoice);

            return $invoice;
        });
    }

    /**
     * Record a payment against the invoice.
     */
    public function recordPayment(Invoice $invoice, array $paymentData): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            $payment = $invoice->payments()->create($paymentData);

            $this->updateInvoiceBalance($invoice);
            $this->journalService->createPaymentEntry($payment);

            return $payment;
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Private Methods
    // ─────────────────────────────────────────────────────────────

    private function createItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $invoice->items()->create([
                'product_id' => $item['product_id'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price'],
            ]);
        }
    }

    private function calculateTotals(Invoice $invoice): void
    {
        $subtotal = $invoice->items()->sum('subtotal');
        $taxAmount = $this->calculateTax($subtotal);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'amount_due' => $subtotal + $taxAmount - $invoice->amount_paid,
        ]);
    }

    private function calculateTax(int $amount): int
    {
        $rate = config('accounting.tax.ppn.rate') / 100;
        return (int) round($amount * $rate);
    }

    private function ensureCanEdit(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new BusinessException(
                'INVOICE_NOT_EDITABLE',
                'Only draft invoices can be edited.'
            );
        }
    }

    private function ensureCanApprove(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new BusinessException(
                'INVOICE_ALREADY_APPROVED',
                'Invoice is not in draft status.'
            );
        }

        if ($invoice->items->isEmpty()) {
            throw new BusinessException(
                'INVOICE_NO_ITEMS',
                'Invoice must have at least one item.'
            );
        }
    }

    private function generateNumber(): string
    {
        return app(DocumentNumberService::class)->generate('invoice');
    }

    private function createJournalEntry(Invoice $invoice): void
    {
        $this->journalService->create([
            'date' => $invoice->date,
            'description' => "Invoice {$invoice->number}",
            'source' => $invoice,
            'lines' => [
                ['account' => 'accounts_receivable', 'debit' => $invoice->total],
                ['account' => 'sales_revenue', 'credit' => $invoice->subtotal],
                ['account' => 'ppn_output', 'credit' => $invoice->tax_amount],
            ],
        ]);
    }
}
```

---

## Key Principles

### 1. Constructor Injection

```php
public function __construct(
    private JournalEntryService $journalService,
    private InventoryService $inventoryService
) {}
```

### 2. Transaction Wrapping

```php
return DB::transaction(function () use ($data) {
    // Multiple database operations
    // All succeed or all fail
});
```

### 3. Validation in Service

```php
private function ensureCanApprove(Invoice $invoice): void
{
    if ($invoice->status !== 'draft') {
        throw new BusinessException(...);
    }
}
```

### 4. Event Coordination

```php
public function complete(WorkOrder $workOrder): void
{
    DB::transaction(function () use ($workOrder) {
        $workOrder->update(['status' => 'completed']);

        // Coordinate with other services
        $this->inventoryService->incrementStock(...);
        $this->costService->allocateToProject(...);
    });
}
```

---

## Service Method Naming

| Action | Method Name | Returns |
|--------|-------------|---------|
| Create | `create(array $data)` | Model |
| Update | `update(Model $model, array $data)` | Model |
| Delete | `delete(Model $model)` | bool |
| Status change | `approve(Model $model)` | Model |
| Business action | `recordPayment(...)` | Model |
| Query | `getByStatus(string $status)` | Collection |
| Calculate | `calculateTotals(Model $model)` | void |

---

## Error Handling

```php
// Custom business exception
throw new BusinessException(
    errorCode: 'INSUFFICIENT_STOCK',
    message: 'Not enough stock to fulfill order.',
    details: ['available' => 10, 'requested' => 15]
);
```

---

## Related Documents

- [ADR-0003: Service Layer Pattern](../08-adr/0003-service-layer-pattern.md)
- [Service Layer Architecture](../01-architecture/service-layer.md)
- [Controller Pattern](./controller-pattern.md)

