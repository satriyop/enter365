---
pattern: service
title: "Service Pattern"
location: app/Services/
tags: [architecture, services]
updated: 2026-01-15
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

## Service Architecture Overview

```
app/
├── Contracts/
│   └── Services/               # Service interfaces
│       ├── DocumentLifecycleInterface.php
│       ├── FinancialCalculationInterface.php
│       ├── WorkflowServiceInterface.php
│       └── Domains/
│           ├── QuotationServiceInterface.php
│           ├── BomServiceInterface.php
│           └── MrpServiceInterface.php
├── Services/
│   ├── Base/                   # Abstract base classes
│   │   ├── AbstractDocumentService.php
│   │   └── AbstractReportService.php
│   ├── Accounting/             # Core accounting services
│   │   └── Reports/            # Report services + factory
│   ├── Sales/                  # Sales domain
│   ├── Purchasing/             # Purchasing domain
│   ├── Inventory/              # Inventory domain
│   ├── Manufacturing/          # Manufacturing domain
│   ├── Projects/               # Project domain
│   └── Solar/                  # Solar EPC domain
```

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

## Service Interfaces

Services implement interfaces for better testability and dependency inversion:

```php
// app/Contracts/Services/DocumentLifecycleInterface.php
namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

interface DocumentLifecycleInterface
{
    public function create(array $data): Model;
    public function update(Model $model, array $data): Model;
    public function delete(Model $model): bool;
    public function post(Model $model): Model;
    public function void(Model $model): Model;
}
```

### Binding Interfaces

```php
// AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        QuotationServiceInterface::class,
        QuotationService::class
    );
}
```

### Using Interfaces in Controllers

```php
public function __construct(
    private QuotationServiceInterface $quotationService
) {}
```

---

## Service Factory Pattern

For controllers with many service dependencies, use a factory:

```php
// app/Services/Accounting/Reports/ReportServiceFactory.php
namespace App\Services\Accounting\Reports;

class ReportServiceFactory
{
    public const TYPE_FINANCIAL = 'financial';
    public const TYPE_AGING = 'aging';
    public const TYPE_TAX = 'tax';

    public function make(string $type): object
    {
        return match ($type) {
            self::TYPE_FINANCIAL => app(FinancialReportService::class),
            self::TYPE_AGING => app(AgingReportService::class),
            self::TYPE_TAX => app(TaxReportService::class),
            // ...
            default => throw new InvalidArgumentException("Unknown report type: {$type}"),
        };
    }

    // Typed accessor methods
    public function financial(): FinancialReportService
    {
        return $this->make(self::TYPE_FINANCIAL);
    }

    public function aging(): AgingReportService
    {
        return $this->make(self::TYPE_AGING);
    }
}
```

### Using Factory in Controller

```php
// Before: 10 dependencies
public function __construct(
    private FinancialReportService $financialService,
    private AgingReportService $agingService,
    private TaxReportService $taxService,
    // ... 7 more
) {}

// After: 1 dependency with lazy loading
public function __construct(
    private ReportServiceFactory $reports
) {}

public function aging(): JsonResponse
{
    return response()->json(
        $this->reports->aging()->generate($dateRange)
    );
}
```

---

## Related Documents

- [ADR-0003: Service Layer Pattern](../08-adr/0003-service-layer-pattern.md)
- [Service Layer Architecture](../01-architecture/service-layer.md)
- [Controller Pattern](./controller-pattern.md)

