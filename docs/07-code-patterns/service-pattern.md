---
pattern: service
title: "Service Pattern"
location: app/Services/
tags: [architecture, services]
updated: 2026-01-16
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
│       └── Domains/            # 23 domain-specific interfaces
│           ├── InvoiceServiceInterface.php
│           ├── BillServiceInterface.php
│           ├── QuotationServiceInterface.php
│           ├── PurchaseOrderServiceInterface.php
│           ├── BomServiceInterface.php
│           ├── WorkOrderServiceInterface.php
│           ├── MrpServiceInterface.php
│           └── ... (16 more)
├── Exceptions/
│   └── Domain/                 # Domain exceptions
│       ├── DomainException.php
│       ├── ValidationException.php
│       ├── StateTransitionException.php
│       ├── InsufficientStockException.php
│       └── DocumentLockedException.php
├── Services/
│   ├── Base/                   # Base service and traits
│   │   ├── BaseService.php     # Core service class
│   │   ├── AbstractDocumentService.php  # Legacy (deprecated)
│   │   └── Traits/             # Composable traits
│   │       ├── WithTransaction.php
│   │       ├── WithEventDispatching.php
│   │       ├── WithOperationContext.php
│   │       └── WithDocuments.php
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

**Current Architecture:** All services extend `BaseService` with composable traits.

```php
<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Services\Domains\InvoiceServiceInterface;
use App\Models\Sales\Invoice;
use App\Services\Accounting\JournalService;
use App\Services\Inventory\InventoryService;
use App\Services\Base\BaseService;

class InvoiceService extends BaseService implements InvoiceServiceInterface
{
    public function __construct(
        private JournalService $journalService,
        private InventoryService $inventoryService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new invoice with items.
     */
    public function create(array $data): Invoice
    {
        // BaseService provides executeInTransaction() via WithTransaction trait
        return $this->executeInTransaction('create_invoice', function () use ($data) {
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

        return $this->executeInTransaction('update_invoice', function () use ($invoice, $data) {
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

        return $this->executeInTransaction('approve_invoice', function () use ($invoice) {
            $invoice->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->getUserId(), // From WithOperationContext trait
            ]);

            $this->createJournalEntry($invoice);

            // Dispatch domain event (from WithEventDispatching trait)
            $this->dispatch(new InvoiceApproved($invoice));

            return $invoice;
        });
    }

    /**
     * Record a payment against the invoice.
     */
    public function recordPayment(Invoice $invoice, array $paymentData): Payment
    {
        return $this->executeInTransaction('record_payment', function () use ($invoice, $paymentData) {
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
            throw new DocumentLockedException(
                'Invoice',
                $invoice->id,
                'Only draft invoices can be edited.'
            );
        }
    }

    private function ensureCanApprove(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new StateTransitionException(
                'Invoice',
                $invoice->status,
                'approved',
                'Invoice is not in draft status.'
            );
        }

        if ($invoice->items->isEmpty()) {
            throw new ValidationException(
                'Invoice must have at least one item.',
                ['items' => 'At least one item is required']
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

### 1. Extend BaseService

All services extend `BaseService` which provides core functionality:

```php
use App\Services\Base\BaseService;

class MyService extends BaseService
{
    // Automatically includes:
    // - WithTransaction trait (executeInTransaction, execute)
    // - WithEventDispatching trait (dispatch)
    // - WithOperationContext trait (getContext, getUserId)
}
```

### 2. Constructor Injection

```php
public function __construct(
    private JournalEntryService $journalService,
    private InventoryService $inventoryService,
    EventDispatcherInterface $eventDispatcher,
    ContextualLoggerInterface $logger
) {
    parent::__construct($eventDispatcher, $logger);
}
```

### 3. Transaction Wrapping

Use `executeInTransaction()` from `WithTransaction` trait:

```php
return $this->executeInTransaction('operation_name', function () use ($data) {
    // Multiple database operations
    // All succeed or all fail
    // Automatic logging and performance tracking
});
```

### 4. Validation in Service

```php
private function ensureCanApprove(Invoice $invoice): void
{
    if ($invoice->status !== 'draft') {
        throw new BusinessException(...);
    }
}
```

### 5. Event Coordination

Use `dispatch()` from `WithEventDispatching` trait:

```php
public function complete(WorkOrder $workOrder): void
{
    $this->executeInTransaction('complete_work_order', function () use ($workOrder) {
        $workOrder->update(['status' => 'completed']);

        // Coordinate with other services
        $this->inventoryService->incrementStock(...);
        $this->costService->allocateToProject(...);

        // Dispatch domain event
        $this->dispatch(new WorkOrderCompleted($workOrder));
    });
}
```

### 6. Operation Context

Use `getUserId()` and `getContext()` from `WithOperationContext` trait:

```php
public function createRecord(array $data)
{
    $data['created_by'] = $this->getUserId(); // From OperationContext
    $data['tenant_id'] = $this->getTenantId(); // Future multi-tenant

    return Model::create($data);
}
```

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

## Domain Exceptions

Use semantic domain exceptions instead of generic `InvalidArgumentException`:

```php
use App\Exceptions\Domain\ValidationException;
use App\Exceptions\Domain\StateTransitionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\DocumentLockedException;
```

### Exception Types

| Exception | Use Case | Example |
|-----------|----------|---------|
| `ValidationException` | Business rule violations | Missing required items |
| `StateTransitionException` | Invalid workflow transitions | Approving non-draft document |
| `InsufficientStockException` | Stock availability issues | Not enough inventory |
| `DocumentLockedException` | Editing posted/locked documents | Modifying approved invoice |

### Usage Examples

```php
// Validation failure
throw new ValidationException(
    message: 'Invoice must have at least one item.',
    errors: ['items' => 'At least one item is required']
);

// Invalid state transition
throw new StateTransitionException(
    entity: 'WorkOrder',
    currentState: 'draft',
    targetState: 'completed',
    message: 'Work order must be started before completion.'
);

// Insufficient stock
throw new InsufficientStockException(
    productId: $product->id,
    productName: $product->name,
    requested: 100,
    available: 50
);

// Document locked
throw new DocumentLockedException(
    documentType: 'Invoice',
    documentId: $invoice->id,
    message: 'Posted invoices cannot be modified.'
);
```

### API Response Format

Domain exceptions return structured JSON responses:

```json
{
    "code": "INSUFFICIENT_STOCK",
    "message": "Stok tidak mencukupi untuk transfer.",
    "context": {
        "product_id": 123,
        "requested": 100,
        "available": 50
    }
}
```

---

## Service Interfaces

All 23 domain services implement interfaces for testability and dependency inversion.

### Complete Interface List

| Domain | Interface | Service |
|--------|-----------|---------|
| **Sales** | `InvoiceServiceInterface` | `InvoiceService` |
| | `QuotationServiceInterface` | `QuotationService` |
| | `DeliveryOrderServiceInterface` | `DeliveryOrderService` |
| | `DownPaymentServiceInterface` | `DownPaymentService` |
| | `SalesReturnServiceInterface` | `SalesReturnService` |
| | `RecurringServiceInterface` | `RecurringService` |
| **Purchasing** | `BillServiceInterface` | `BillService` |
| | `PurchaseOrderServiceInterface` | `PurchaseOrderService` |
| | `GoodsReceiptNoteServiceInterface` | `GoodsReceiptNoteService` |
| | `PurchaseReturnServiceInterface` | `PurchaseReturnService` |
| **Manufacturing** | `BomServiceInterface` | `BomService` |
| | `BomTemplateServiceInterface` | `BomTemplateService` |
| | `BomVariantGroupServiceInterface` | `BomVariantGroupService` |
| | `WorkOrderServiceInterface` | `WorkOrderService` |
| | `MaterialRequisitionServiceInterface` | `MaterialRequisitionService` |
| | `MrpServiceInterface` | `MrpService` |
| | `SubcontractorServiceInterface` | `SubcontractorService` |
| **Inventory** | `InventoryServiceInterface` | `InventoryService` |
| | `StockOpnameServiceInterface` | `StockOpnameService` |
| | `ProductServiceInterface` | `ProductService` |
| **Projects** | `ProjectServiceInterface` | `ProjectService` |
| **Solar** | `SolarProposalServiceInterface` | `SolarProposalService` |
| | `SolarCalculationServiceInterface` | `SolarCalculationService` |

### Interface Definition Example

```php
// app/Contracts/Services/Domains/InvoiceServiceInterface.php
namespace App\Contracts\Services\Domains;

use App\Contracts\Services\DocumentLifecycleInterface;
use App\Models\Sales\Invoice;

interface InvoiceServiceInterface extends DocumentLifecycleInterface
{
    /**
     * Post an invoice to the journal.
     */
    public function post(Invoice $invoice): Invoice;
}
```

### Service Implementation

```php
// app/Services/Sales/InvoiceService.php
namespace App\Services\Sales;

use App\Contracts\Services\Domains\InvoiceServiceInterface;
use App\Services\Base\BaseService;
use App\Services\Base\Traits\WithDocuments;

class InvoiceService extends BaseService implements InvoiceServiceInterface
{
    use WithDocuments;

    // Implementation...
    // WithDocuments trait provides document lifecycle methods
}
```

**Note:** `AbstractDocumentService` is deprecated. Use `BaseService + WithDocuments` trait instead.

### Binding Interfaces (AppServiceProvider)

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    // Sales
    $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
    $this->app->bind(QuotationServiceInterface::class, QuotationService::class);

    // Purchasing
    $this->app->bind(BillServiceInterface::class, BillService::class);
    $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);

    // Manufacturing
    $this->app->bind(BomServiceInterface::class, BomService::class);
    $this->app->bind(WorkOrderServiceInterface::class, WorkOrderService::class);

    // ... all 23 bindings
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

