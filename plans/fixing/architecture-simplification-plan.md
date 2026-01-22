# Architecture Simplification Plan

**Created:** Jan 2026
**Status:** Proposed
**Goal:** Remove over-engineering, keep patterns that add real value

---

## Executive Summary

After honest code review, we identified patterns that add complexity without proportional value. This plan removes dead code and focuses investment on high-impact patterns.

| Action | Impact | Effort |
|--------|--------|--------|
| Delete unused repositories | Reduces confusion | Low |
| Remove interfaces from simple services | Reduces boilerplate | Low |
| Add Domain Factories (Invoice, WorkOrder, PurchaseOrder) | High value | Medium |
| Simplify OperationContext usage | Reduces ceremony | Low |

---

## Phase 1: Delete Dead Code (Low Effort, Immediate)

### 1.1 Delete Unused Repositories

**Problem:** WorkOrderRepository and ProductStockRepository are bound but never injected.

**Files to delete:**
```
app/Contracts/Repositories/Manufacturing/WorkOrderRepositoryInterface.php
app/Contracts/Repositories/Inventory/ProductStockRepositoryInterface.php
app/Infrastructure/Repositories/Manufacturing/EloquentWorkOrderRepository.php
app/Infrastructure/Repositories/Inventory/EloquentProductStockRepository.php
```

**Update:**
```php
// app/Providers/RepositoryServiceProvider.php
// REMOVE these lines:
WorkOrderRepositoryInterface::class => EloquentWorkOrderRepository::class,
ProductStockRepositoryInterface::class => EloquentProductStockRepository::class,
```

**Verification:**
```bash
# Should return 0 results after deletion
grep -rn "WorkOrderRepositoryInterface\|ProductStockRepositoryInterface" app/
```

**Keep:** QuotationRepository and InvoiceRepository (actually used).

---

### 1.2 Remove Interfaces from Simple Services

**Criteria for removal:** Service has no alternative implementations, is simple CRUD, or is internal utility.

**Services to remove interfaces from:**

| Service | Interface to Delete | Reason |
|---------|---------------------|--------|
| `QuotationFollowUpService` | N/A (no interface) | Already correct |
| `CostOptimizationService` | N/A (no interface) | Already correct |
| `QuotationStatisticsService` | N/A (no interface) | Already correct |
| `RecurringService` | `RecurringServiceInterface` | Simple utility, no alternatives |
| `DownPaymentService` | `DownPaymentServiceInterface` | Internal, no alternatives |

**Decision needed:** Review each interface and ask "Will we ever have a different implementation?" If no, delete.

**Action:**
1. Find services with interfaces that are simple utilities
2. Update service to not implement interface
3. Update any DI bindings to use concrete class
4. Delete interface file

---

## Phase 2: High-Value Domain Factories (Medium Effort, High Impact) ✅ COMPLETE

### 2.1 Invoice Domain Factory

**Why high impact:**
- Complex state machine (Draft → Sent → Partial → Paid → Overdue → Cancelled)
- Tax and total calculations
- Journal entry integration
- Payment tracking

**Create:** `app/Domain/Sales/Invoices/InvoiceDomainFactory.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Models\Sales\Invoice;

class InvoiceDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private InvoiceCalculatorInterface $calculator,
    ) {}

    /**
     * Create state machine with event dispatcher for mutations.
     */
    public function stateMachine(Invoice $invoice): InvoiceStateMachine
    {
        return InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);
    }

    /**
     * Calculate invoice totals.
     */
    public function calculateTotals(Invoice $invoice): InvoiceTotals
    {
        $lineTotals = $invoice->items->pluck('line_total')->toArray();

        return $this->calculator->calculate(
            $lineTotals,
            (float) ($invoice->tax_rate ?? 0),
            $invoice->discount_type,
            (float) ($invoice->discount_value ?? 0),
            $invoice->currency,
            (float) ($invoice->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to invoice (without saving).
     */
    public function applyTotals(Invoice $invoice): Invoice
    {
        $totals = $this->calculateTotals($invoice);

        $invoice->subtotal = $totals->subtotal;
        $invoice->discount_amount = $totals->discountAmount;
        $invoice->tax_amount = $totals->taxAmount;
        $invoice->total_amount = $totals->totalAmount;

        return $invoice;
    }

    /**
     * Calculate outstanding amount.
     */
    public function calculateOutstanding(Invoice $invoice): int
    {
        return $invoice->total_amount - $invoice->paid_amount;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isFullyPaid(Invoice $invoice): bool
    {
        return $this->calculateOutstanding($invoice) <= 0;
    }
}
```

**Create DTO:** `app/Domain/Sales/Invoices/InvoiceTotals.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

final readonly class InvoiceTotals
{
    public function __construct(
        public int $subtotal,
        public int $discountAmount,
        public int $taxAmount,
        public int $totalAmount,
        public int $baseCurrencyTotal,
    ) {}
}
```

**Register:** Add to `AppServiceProvider::register()`
```php
$this->app->singleton(InvoiceDomainFactory::class);
```

**Update InvoiceService** to use factory instead of inline logic.

---

### 2.2 WorkOrder Domain Factory

**Why high impact:**
- Complex state machine (Draft → Confirmed → InProgress → Completed → Cancelled)
- Material cost calculations
- Labor cost calculations
- BOM explosion
- Material requisition creation

**Create:** `app/Domain/Manufacturing/WorkOrders/WorkOrderDomainFactory.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Models\Manufacturing\WorkOrder;

class WorkOrderDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function stateMachine(WorkOrder $workOrder): WorkOrderStateMachine
    {
        return WorkOrderStateMachine::fromWorkOrder($workOrder, $this->eventDispatcher);
    }

    /**
     * Calculate total material cost from work order items.
     */
    public function calculateMaterialCost(WorkOrder $workOrder): int
    {
        return $workOrder->items
            ->where('type', 'material')
            ->sum(fn ($item) => $item->quantity_required * $item->unit_cost);
    }

    /**
     * Calculate total labor cost.
     */
    public function calculateLaborCost(WorkOrder $workOrder): int
    {
        return $workOrder->items
            ->where('type', 'labor')
            ->sum(fn ($item) => $item->quantity_required * $item->unit_cost);
    }

    /**
     * Calculate total planned cost.
     */
    public function calculatePlannedCost(WorkOrder $workOrder): WorkOrderCosts
    {
        return new WorkOrderCosts(
            materialCost: $this->calculateMaterialCost($workOrder),
            laborCost: $this->calculateLaborCost($workOrder),
            overheadCost: $workOrder->overhead_cost ?? 0,
        );
    }

    /**
     * Apply costs to work order (without saving).
     */
    public function applyCosts(WorkOrder $workOrder): WorkOrder
    {
        $costs = $this->calculatePlannedCost($workOrder);

        $workOrder->planned_material_cost = $costs->materialCost;
        $workOrder->planned_labor_cost = $costs->laborCost;
        $workOrder->planned_total_cost = $costs->totalCost;

        return $workOrder;
    }
}
```

**Create DTO:** `app/Domain/Manufacturing/WorkOrders/WorkOrderCosts.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

final readonly class WorkOrderCosts
{
    public int $totalCost;

    public function __construct(
        public int $materialCost,
        public int $laborCost,
        public int $overheadCost,
    ) {
        $this->totalCost = $materialCost + $laborCost + $overheadCost;
    }
}
```

---

### 2.3 PurchaseOrder Domain Factory

**Why high impact:**
- Complex state machine (Draft → Submitted → Approved → Partial → Received → Cancelled)
- Tax and total calculations
- Conversion to Bill
- Conversion to GRN (Goods Receipt Note)

**Create:** `app/Domain/Purchasing/PurchaseOrders/PurchaseOrderDomainFactory.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Purchasing\PurchaseOrderCalculatorInterface;
use App\Models\Purchasing\PurchaseOrder;

class PurchaseOrderDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private PurchaseOrderCalculatorInterface $calculator,
    ) {}

    public function stateMachine(PurchaseOrder $po): PurchaseOrderStateMachine
    {
        return PurchaseOrderStateMachine::fromPurchaseOrder($po, $this->eventDispatcher);
    }

    public function calculateTotals(PurchaseOrder $po): PurchaseOrderTotals
    {
        $lineTotals = $po->items->pluck('line_total')->toArray();

        return $this->calculator->calculate(
            $lineTotals,
            (float) ($po->tax_rate ?? 0),
            $po->discount_type,
            (float) ($po->discount_value ?? 0),
            $po->currency,
            (float) ($po->exchange_rate ?? 1)
        );
    }

    public function applyTotals(PurchaseOrder $po): PurchaseOrder
    {
        $totals = $this->calculateTotals($po);

        $po->subtotal = $totals->subtotal;
        $po->discount_amount = $totals->discountAmount;
        $po->tax_amount = $totals->taxAmount;
        $po->total = $totals->totalAmount;

        return $po;
    }

    /**
     * Calculate unreceived quantity for each item.
     */
    public function getUnreceivedItems(PurchaseOrder $po): array
    {
        return $po->items
            ->filter(fn ($item) => $item->quantity_ordered > $item->quantity_received)
            ->map(fn ($item) => [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'unreceived' => $item->quantity_ordered - $item->quantity_received,
            ])
            ->values()
            ->all();
    }
}
```

---

## Phase 3: Simplify OperationContext (Low Effort)

### 3.1 Document When to Actually Use It

**Current problem:** OperationContext is 215 lines solving what `auth()->id()` does.

**When OperationContext IS valuable:**
- Queue jobs (no auth context)
- Artisan commands (no auth context)
- Multi-tenant future (tenant scoping)
- Audit trails (IP address, timestamp)

**When OperationContext is overkill:**
- Simple HTTP requests (just use `auth()->id()` via middleware)
- Read-only operations (don't need user tracking)

### 3.2 Simplify the Pattern

**Option A: Keep as-is but document clearly**

Update SKILL.md to be honest:
```markdown
### OperationContext - When to Use

| Context | What to Use |
|---------|-------------|
| HTTP requests | Automatic via middleware (don't think about it) |
| Queue jobs | `OperationContext::forJob($userId)` |
| Commands | `OperationContext::forCommand($name)` |
| Tests | Bind to container or use `withContext()` |

For simple services, `$this->getUserId()` just returns `auth()->id()` -
the abstraction is for edge cases, not everyday use.
```

**Option B: Remove from simple services (more aggressive)**

For services that will NEVER run in jobs/commands:
- Remove `$this->getUserId()` calls
- Use `auth()->id()` directly
- Keep OperationContext only for services that genuinely need it

---

## Phase 4: Update Skills Documentation

After implementing changes, update:

1. **SKILL.md** - Remove/simplify gotchas about patterns we're deleting
2. **ARCHITECTURE_PATTERNS.md** - Focus on patterns that remain
3. **SERVICE_BINDINGS.md** - Remove deleted interface bindings
4. **REPOSITORIES.md** - Update to reflect only Quotation & Invoice remain

---

## Implementation Order

| Order | Task | Effort | Risk |
|-------|------|--------|------|
| 1 | Delete unused repositories | 30 min | None |
| 2 | Create InvoiceDomainFactory | 2 hours | Low |
| 3 | Create WorkOrderDomainFactory | 2 hours | Low |
| 4 | Create PurchaseOrderDomainFactory | 2 hours | Low |
| 5 | Update services to use factories | 4 hours | Medium |
| 6 | Remove interfaces from simple services | 1 hour | Low |
| 7 | Update documentation | 1 hour | None |

**Total estimated effort:** 1-2 days

---

## Success Metrics

After implementation:

| Metric | Before | After |
|--------|--------|-------|
| Repository files | 8 | 4 |
| Unused bound interfaces | 2 | 0 |
| Domain Factories | 1 | 4 |
| Services with state machines using factories | 1/15 | 4/15 |

---

## Files to Create

```
app/Domain/Sales/Invoices/InvoiceDomainFactory.php
app/Domain/Sales/Invoices/InvoiceTotals.php
app/Domain/Manufacturing/WorkOrders/WorkOrderDomainFactory.php
app/Domain/Manufacturing/WorkOrders/WorkOrderCosts.php
app/Domain/Purchasing/PurchaseOrders/PurchaseOrderDomainFactory.php
app/Domain/Purchasing/PurchaseOrders/PurchaseOrderTotals.php
```

## Files to Delete

```
app/Contracts/Repositories/Manufacturing/WorkOrderRepositoryInterface.php
app/Contracts/Repositories/Inventory/ProductStockRepositoryInterface.php
app/Infrastructure/Repositories/Manufacturing/EloquentWorkOrderRepository.php
app/Infrastructure/Repositories/Inventory/EloquentProductStockRepository.php
```

## Files to Modify

```
app/Providers/RepositoryServiceProvider.php  (remove unused bindings)
app/Providers/AppServiceProvider.php          (add factory singletons)
app/Services/Sales/InvoiceService.php         (use InvoiceDomainFactory)
app/Services/Manufacturing/WorkOrderService.php (use WorkOrderDomainFactory)
app/Services/Purchasing/PurchaseOrderService.php (use PurchaseOrderDomainFactory)
```

---

## Decision Log

| Decision | Rationale |
|----------|-----------|
| Keep Quotation & Invoice repositories | Actually used, provide value |
| Delete WorkOrder & ProductStock repositories | Never injected, dead code |
| Add Domain Factories for 3 models | Complex state + calculations = high ROI |
| Don't add Domain Factory for simple models | DeliveryOrder, StockOpname don't need it |
| Keep OperationContext infrastructure | Useful for jobs/commands, low maintenance cost |
