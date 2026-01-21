# Service Bindings Quick Reference

Quick lookup for which interface to inject and what service it resolves to.

---

## Infrastructure Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `EventDispatcherInterface` | `LaravelEventDispatcher` | Domain event dispatching |
| `FeatureManager` | `ConfigFeatureManager` | Feature flag management |

---

## Accounting Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `JournalServiceInterface` | `JournalService` | Journal entry CRUD |
| `AccountLookupServiceInterface` | `AccountLookupService` | Chart of accounts lookup |

### Accounting Strategies

| Interface | Resolved Via | Purpose |
|-----------|--------------|---------|
| `InventoryAccountingStrategy` | `AccountingPolicyManager->inventory()` | FIFO/Average/Standard costing |
| `COGSRecognitionStrategy` | `AccountingPolicyManager->cogs()` | When to recognize COGS |
| `ReturnAccountingStrategy` | `AccountingPolicyManager->returns()` | Return credit handling |
| `ManufacturingCostStrategy` | `AccountingPolicyManager->manufacturing()` | Production costing |

---

## Sales Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `InvoiceServiceInterface` | `InvoiceService` | Invoice CRUD, send, void |
| `QuotationServiceInterface` | `QuotationService` | Quotation CRUD, submit, approve |
| `QuotationConversionServiceInterface` | `QuotationConversionService` | Quotation → Invoice conversion |
| `DownPaymentServiceInterface` | `DownPaymentService` | Down payment management |
| `DeliveryOrderServiceInterface` | `DeliveryOrderService` | DO CRUD, confirm, ship, deliver |
| `SalesReturnServiceInterface` | `SalesReturnService` | Sales returns with approval |
| `RecurringServiceInterface` | `RecurringService` | Recurring document generation |
| `QuotationCalculatorInterface` | `QuotationCalculator` | Quotation total calculations |

### Quotation Services (Coordinator Pattern)

The QuotationService uses the Coordinator Pattern, delegating to focused services:

| Class | Purpose | Dependencies |
|-------|---------|--------------|
| `QuotationService` | Thin coordinator (197 lines) | Crud + Workflow + Statistics + Conversion |
| `Quotation\QuotationCrudService` | CRUD operations (241 lines) | Repository, Defaults, ItemCreator, DomainFactory |
| `Quotation\QuotationWorkflowService` | State transitions (205 lines) | Repository, DomainFactory |
| `Quotation\QuotationStatisticsService` | Statistics (67 lines) | QuotationStatistics domain class |

**Note:** No additional interface bindings needed - Laravel auto-resolves concrete classes.

**Usage options:**
```php
// Via coordinator (backward compatible)
app(QuotationServiceInterface::class)->create($data);

// Direct use (when you only need CRUD)
app(QuotationCrudService::class)->create($data);
```

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#real-world-example-quotationservice-refactoring)

### Quotation Follow-Up & CRM Services

Services for quotation tracking, follow-ups, and outcomes (win/loss). No interfaces - use concrete classes directly.

| Class | Purpose | Base Class |
|-------|---------|------------|
| `QuotationFollowUpService` | Schedule follow-ups, record contacts | `AbstractApplicationService` |
| `QuotationOutcomeService` | Mark quotations as won/lost | `AbstractApplicationService` |

**Note:** `QuotationOutcomeService` was renamed from `QuotationWorkflowService` (Jan 2026) to avoid naming collision with `Quotation\QuotationWorkflowService` which handles state transitions.

**Usage:**
```php
// Inject directly - no interface needed
public function __construct(
    private QuotationOutcomeService $outcomeService,
    private QuotationFollowUpService $followUpService,
) {}

// Mark as won/lost
$this->outcomeService->markAsWon($quotation, ['won_reason' => 'price']);
$this->outcomeService->markAsLost($quotation, ['lost_reason' => 'competitor']);
```

---

## Purchasing Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `BillServiceInterface` | `BillService` | Bill CRUD, receive, void |
| `PurchaseOrderServiceInterface` | `PurchaseOrderService` | PO CRUD, submit, approve |
| `GoodsReceiptNoteServiceInterface` | `GoodsReceiptNoteService` | GRN CRUD, receive goods |
| `PurchaseReturnServiceInterface` | `PurchaseReturnService` | Purchase returns with approval |
| `PurchaseOrderCalculatorInterface` | `PurchaseOrderCalculator` | PO total calculations |

---

## Manufacturing Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `BomServiceInterface` | `BomService` | BOM CRUD, activate |
| `BomTemplateServiceInterface` | `BomTemplateService` | BOM templates |
| `BomVariantGroupServiceInterface` | `BomVariantGroupService` | Multi-brand alternatives |
| `WorkOrderServiceInterface` | `WorkOrderService` | Work order CRUD, start, complete |
| `MaterialRequisitionServiceInterface` | `MaterialRequisitionService` | Material requisitions |
| `MrpServiceInterface` | `MrpService` | MRP planning |
| `SubcontractorServiceInterface` | `SubcontractorService` | Subcontractor work orders |

### Brand Swap Services (Coordinator Pattern)

The BrandSwapService uses the Coordinator Pattern, delegating to focused services:

| Class | Purpose | Dependencies |
|-------|---------|--------------|
| `BrandSwapService` | Thin coordinator (124 lines) | Preview + Execution services |
| `BrandSwap\BrandSwapPreviewService` | Read-only previews (310 lines) | SpecValidationService |
| `BrandSwap\BrandSwapExecutionService` | Write operations (342 lines) | EquivalenceService, VariantGroupService |

**Note:** No interface bindings needed - Laravel auto-resolves concrete classes.

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#coordinator-pattern-for-god-services)

---

## Inventory Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `InventoryServiceInterface` | `InventoryService` | Stock movements, transfers |
| `StockOpnameServiceInterface` | `StockOpnameService` | Physical inventory counts |
| `ProductServiceInterface` | `ProductService` | Product CRUD |

---

## Projects Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `ProjectServiceInterface` | `ProjectService` | Project CRUD, lifecycle |

---

## Solar Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `SolarProposalServiceInterface` | `SolarProposalService` | Solar proposal CRUD |
| `SolarCalculationServiceInterface` | `SolarCalculationService` | ROI, savings calculations |

---

## Shared Services

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `PaymentServiceInterface` | `PaymentService` | Payment recording |

---

## Repositories

Repositories provide a consistent API for data access, enabling testability and decoupling from Eloquent.

**Provider:** `RepositoryServiceProvider`

| Interface | Implementation | Purpose |
|-----------|----------------|---------|
| `InvoiceRepositoryInterface` | `EloquentInvoiceRepository` | Invoice queries, find by number/status |
| `QuotationRepositoryInterface` | `EloquentQuotationRepository` | Quotation queries, follow-ups, pipeline stats |
| `WorkOrderRepositoryInterface` | `EloquentWorkOrderRepository` | Work order queries |
| `ProductStockRepositoryInterface` | `EloquentProductStockRepository` | Product stock queries |

---

## Domain Factories (Singletons)

Domain factories are registered as **singletons** because they cache internal managers.

| Class | Purpose | Dependencies |
|-------|---------|--------------|
| `QuotationDomainFactory` | Creates state machines, managers, calculators for Quotation | `EventDispatcherInterface`, `QuotationCalculatorInterface` |

**Usage in services:**

```php
class QuotationService
{
    public function __construct(
        private QuotationDomainFactory $domainFactory,
    ) {}

    public function submit(Quotation $quotation): Quotation
    {
        $stateMachine = $this->domainFactory->stateMachine($quotation);
        // ...
    }
}
```

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#domain-factory-pattern)

---

## Approval Pipelines

| Class | Handlers | Purpose |
|-------|----------|---------|
| `SalesReturnApprovalPipeline` | InventoryReturnHandler, JournalEntryHandler | Sales return processing |
| `PurchaseReturnApprovalPipeline` | InventoryReturnHandler, JournalEntryHandler | Purchase return processing |

---

## Decision Tree: Which Service Do I Need?

### "I need to create/update a document"

| Document | Inject This |
|----------|-------------|
| Invoice | `InvoiceServiceInterface` |
| Quotation | `QuotationServiceInterface` |
| Delivery Order | `DeliveryOrderServiceInterface` |
| Sales Return | `SalesReturnServiceInterface` |
| Bill | `BillServiceInterface` |
| Purchase Order | `PurchaseOrderServiceInterface` |
| GRN | `GoodsReceiptNoteServiceInterface` |
| Purchase Return | `PurchaseReturnServiceInterface` |
| Work Order | `WorkOrderServiceInterface` |
| Material Requisition | `MaterialRequisitionServiceInterface` |
| BOM | `BomServiceInterface` |
| Project | `ProjectServiceInterface` |
| Solar Proposal | `SolarProposalServiceInterface` |

### "I need to work with inventory"

| Task | Inject This |
|------|-------------|
| Stock movements | `InventoryServiceInterface` |
| Stock counts | `StockOpnameServiceInterface` |
| Product management | `ProductServiceInterface` |

### "I need to work with accounting"

| Task | Inject This |
|------|-------------|
| Journal entries | `JournalServiceInterface` |
| Account lookup | `AccountLookupServiceInterface` |
| COGS calculation | `COGSRecognitionStrategy` |
| Inventory costing | `InventoryAccountingStrategy` |

### "I need to calculate totals"

| Document | Inject This |
|----------|-------------|
| Quotation | `QuotationCalculatorInterface` |
| Purchase Order | `PurchaseOrderCalculatorInterface` |

---

## Controller Injection Example

```php
<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Http\Requests\Api\V1\Sales\StoreInvoiceRequest;
use App\Http\Resources\Api\V1\Sales\InvoiceResource;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService
    ) {}

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->invoiceService->create($request->validated());

        return InvoiceResource::make($invoice);
    }

    public function send(Invoice $invoice)
    {
        $invoice = $this->invoiceService->send($invoice);

        return InvoiceResource::make($invoice);
    }
}
```

---

## Service with Multiple Dependencies

```php
<?php

namespace App\Services\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private InventoryServiceInterface $inventoryService,
        private COGSRecognitionStrategy $cogsStrategy,
        private EventDispatcherInterface $eventDispatcher
    ) {}
}
```

---

## Testing with Mocks

```php
<?php

use App\Contracts\Sales\InvoiceServiceInterface;

it('creates invoice', function () {
    // Mock the service
    $mockService = Mockery::mock(InvoiceServiceInterface::class);
    $mockService->shouldReceive('create')->once()->andReturn($invoice);

    $this->app->instance(InvoiceServiceInterface::class, $mockService);

    // Test your code...
});
```
