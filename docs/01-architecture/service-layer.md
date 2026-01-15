---
section: architecture
title: "Service Layer"
order: 3
updated: 2026-01-15
---

# Service Layer Architecture

> **40+ service classes containing all business logic**
>
> Business logic is organized by domain in `/app/Services/`. Controllers are thin, services are smart.

---

## AI Agent Quick Reference

**Use this document when:**
- Adding new business logic (create in service, not controller)
- Understanding where specific logic lives
- Finding the right service for a feature
- Learning the service pattern

**Key takeaway:** Never put business logic in controllers. Create or use a service class.

---

## Service Organization

Services are now organized by domain:

```
app/Services/
├── Accounting/           # Core accounting
│   ├── JournalService.php
│   ├── AccountBalanceService.php
│   ├── FiscalPeriodService.php
│   └── Reports/          # Report services + factory
│       ├── ReportServiceFactory.php
│       ├── FinancialReportService.php
│       ├── AgingReportService.php
│       ├── TaxReportService.php
│       ├── CashFlowReportService.php
│       └── BankReconciliationReportService.php
├── Sales/                # Sales domain
│   ├── QuotationService.php
│   ├── QuotationWorkflowService.php
│   ├── DeliveryOrderService.php
│   ├── SalesReturnService.php
│   ├── DownPaymentService.php
│   ├── RecurringService.php
│   ├── ReminderService.php
│   └── OverdueService.php
├── Purchasing/           # Purchasing domain
│   ├── PurchaseOrderService.php
│   ├── GoodsReceiptNoteService.php
│   └── PurchaseReturnService.php
├── Inventory/            # Inventory domain
│   ├── InventoryService.php
│   ├── StockOpnameService.php
│   └── Reports/
│       └── COGSReportService.php
├── Manufacturing/        # Manufacturing domain
│   ├── BomService.php
│   ├── BomTemplateService.php
│   ├── BomVariantGroupService.php
│   ├── WorkOrderService.php
│   ├── MaterialRequisitionService.php
│   ├── MrpService.php
│   ├── MrpDemandService.php
│   ├── MrpSuggestionService.php
│   ├── ComponentCrossReferenceService.php
│   ├── SpecValidationService.php
│   ├── SubcontractorService.php
│   └── Reports/
│       ├── WorkOrderReportService.php
│       └── SubcontractorReportService.php
├── Projects/             # Project domain
│   ├── ProjectService.php
│   └── ProjectReportService.php
└── Solar/                # Solar EPC domain
    ├── SolarProposalService.php
    └── SolarCalculationService.php
```

---

## Service Pattern

### Controller → Service Flow

```php
// Controller: HTTP concerns only
class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService
    ) {}

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        // 1. Request already validated by Form Request
        // 2. Delegate to service
        $quotation = $this->quotationService->create($request->validated());

        // 3. Return response
        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }
}
```

```php
// Service: Business logic
class QuotationService
{
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            // Generate number
            $data['quotation_number'] = $this->generateNumber();

            // Create record
            $quotation = Quotation::create($data);

            // Sync items
            $this->syncItems($quotation, $data['items'] ?? []);

            // Calculate totals
            $quotation->calculateTotals();

            return $quotation->fresh(['items', 'contact']);
        });
    }
}
```

---

## Service Directory

### Core Accounting (`app/Services/Accounting/`)

| Service | Responsibility |
|---------|----------------|
| `JournalService` | Journal entries, posting, reversals |
| `AccountBalanceService` | Account balance calculations |
| `FiscalPeriodService` | Fiscal year management, period closing |

### Sales (`app/Services/Sales/`)

| Service | Responsibility |
|---------|----------------|
| `QuotationService` | Quotation CRUD, calculations |
| `QuotationWorkflowService` | Status transitions (win/lose) |
| `DeliveryOrderService` | Delivery order processing |
| `SalesReturnService` | Sales returns, credit notes |
| `DownPaymentService` | Down payment tracking, application |
| `RecurringService` | Recurring document generation |
| `ReminderService` | Payment reminders |
| `OverdueService` | Overdue detection |

### Purchasing (`app/Services/Purchasing/`)

| Service | Responsibility |
|---------|----------------|
| `PurchaseOrderService` | PO CRUD, approval workflow |
| `GoodsReceiptNoteService` | GRN processing, stock updates |
| `PurchaseReturnService` | Purchase returns |

### Inventory (`app/Services/Inventory/`)

| Service | Responsibility |
|---------|----------------|
| `InventoryService` | Stock movements, transfers |
| `StockOpnameService` | Physical inventory counts |

### Manufacturing (`app/Services/Manufacturing/`)

| Service | Responsibility |
|---------|----------------|
| `BomService` | Bill of Materials CRUD |
| `BomTemplateService` | Reusable BOM templates |
| `BomVariantGroupService` | Multi-brand variants |
| `WorkOrderService` | Work order lifecycle |
| `MaterialRequisitionService` | Material requests |
| `MrpService` | MRP calculations, demand planning |
| `MrpDemandService` | Demand collection, BOM explosion |
| `MrpSuggestionService` | Suggestion generation, conversion |
| `ComponentCrossReferenceService` | Component alternatives |
| `SpecValidationService` | Component specification validation |
| `SubcontractorService` | Subcontractor management |

### Projects (`app/Services/Projects/`)

| Service | Responsibility |
|---------|----------------|
| `ProjectService` | Project CRUD, cost allocation |

### Solar (`app/Services/Solar/`)

| Service | Responsibility |
|---------|----------------|
| `SolarProposalService` | Solar proposal generation |
| `SolarCalculationService` | ROI, payback calculations |

### Report Services

Report services are accessed via `ReportServiceFactory` for lazy loading:

| Service | Location | Responsibility |
|---------|----------|----------------|
| `FinancialReportService` | `Accounting/Reports/` | Balance sheet, income statement |
| `AgingReportService` | `Accounting/Reports/` | AR/AP aging reports |
| `TaxReportService` | `Accounting/Reports/` | PPN reports |
| `CashFlowReportService` | `Accounting/Reports/` | Cash flow statement |
| `BankReconciliationReportService` | `Accounting/Reports/` | Bank reconciliation |
| `COGSReportService` | `Inventory/Reports/` | Cost of goods sold |
| `ProjectReportService` | `Projects/` | Project profitability |
| `WorkOrderReportService` | `Manufacturing/Reports/` | Work order analytics |
| `SubcontractorReportService` | `Manufacturing/Reports/` | Subcontractor reports |

#### Using ReportServiceFactory

```php
// In ReportController - only 1 dependency instead of 10
public function __construct(
    private ReportServiceFactory $reports
) {}

public function aging(): JsonResponse
{
    return response()->json(
        $this->reports->aging()->generateAgingReport($dateRange)
    );
}

public function financialStatements(): JsonResponse
{
    return response()->json([
        'balance_sheet' => $this->reports->financial()->generateBalanceSheet($date),
        'income_statement' => $this->reports->financial()->generateIncomeStatement($dateRange),
    ]);
}
```

See [Service Pattern: Factory Pattern](../07-code-patterns/service-pattern.md#service-factory-pattern) for implementation details.

---

## Service Patterns

### 1. Transaction Wrapping

All multi-step operations wrapped in transactions:

```php
public function create(array $data): Model
{
    return DB::transaction(function () use ($data) {
        // All operations atomic
        $parent = Parent::create($data);
        $this->syncItems($parent, $data['items']);
        $this->createJournalEntry($parent);
        return $parent->fresh(['items']);
    });
}
```

### 2. Constructor Injection

Dependencies injected via constructor:

```php
class MrpService
{
    public function __construct(
        private BomService $bomService,
        private InventoryService $inventoryService,
        private PurchaseOrderService $poService
    ) {}
}
```

### 3. Return Fresh Models

Always return fresh models with relationships:

```php
return $quotation->fresh(['items', 'contact', 'createdBy']);
```

### 4. Array Shape Documentation

Document complex array parameters:

```php
/**
 * Create a new quotation.
 *
 * @param array{
 *     contact_id: int,
 *     quotation_date: string,
 *     valid_until: string,
 *     items: array<array{product_id: int, quantity: float, unit_price: int}>
 * } $data
 */
public function create(array $data): Quotation
```

### 5. Exception for Business Rules

Throw exceptions for business rule violations:

```php
public function convertToInvoice(Quotation $quotation): Invoice
{
    if (!$quotation->canConvert()) {
        throw new InvalidArgumentException('Quotation cannot be converted.');
    }

    // Proceed with conversion
}
```

---

## Example Service: QuotationService

```php
// File: /app/Services/Accounting/QuotationService.php

namespace App\Services\Accounting;

use App\Models\Accounting\Quotation;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationService
{
    /**
     * Create a new quotation with items.
     */
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $data['quotation_number'] = $this->generateNumber();
            $data['status'] = Quotation::STATUS_DRAFT;

            $quotation = Quotation::create($data);

            $this->syncItems($quotation, $data['items'] ?? []);
            $quotation->calculateTotals();

            return $quotation->fresh(['items', 'contact']);
        });
    }

    /**
     * Update quotation and recalculate totals.
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $quotation->update($data);

            if (isset($data['items'])) {
                $this->syncItems($quotation, $data['items']);
            }

            $quotation->calculateTotals();

            return $quotation->fresh(['items', 'contact']);
        });
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation): Quotation
    {
        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft quotations can be submitted.');
        }

        $quotation->update([
            'status' => Quotation::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return $quotation->fresh();
    }

    /**
     * Approve quotation.
     */
    public function approve(Quotation $quotation): Quotation
    {
        if ($quotation->status !== Quotation::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted quotations can be approved.');
        }

        $quotation->update([
            'status' => Quotation::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return $quotation->fresh();
    }

    /**
     * Convert approved quotation to invoice.
     */
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        if ($quotation->status !== Quotation::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved quotations can be converted.');
        }

        return DB::transaction(function () use ($quotation) {
            $invoice = Invoice::create([
                'contact_id' => $quotation->contact_id,
                'quotation_id' => $quotation->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(config('accounting.payment.default_term_days')),
                'currency' => $quotation->currency,
                'tax_rate' => $quotation->tax_rate,
            ]);

            // Copy items
            foreach ($quotation->items as $item) {
                $invoice->items()->create($item->toArray());
            }

            $invoice->calculateTotals();

            // Mark quotation as converted
            $quotation->update([
                'status' => Quotation::STATUS_CONVERTED,
                'converted_to_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);

            return $invoice;
        });
    }

    /**
     * Generate unique quotation number.
     */
    protected function generateNumber(): string
    {
        $format = config('accounting.document_formats.quotation');
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastNumber = Quotation::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->max('id') ?? 0;

        $sequence = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return str_replace(
            ['{YEAR}', '{MONTH}', '{SEQ}'],
            [$year, $month, $sequence],
            $format
        );
    }

    /**
     * Sync quotation items.
     */
    protected function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach ($items as $index => $item) {
            $quotation->items()->create([
                'product_id' => $item['product_id'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'pcs',
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
```

---

## Testing Services

Services are tested in isolation:

```php
// File: /tests/Unit/Services/QuotationServiceTest.php

use App\Services\Accounting\QuotationService;

it('creates quotation with items', function () {
    $service = app(QuotationService::class);
    $contact = Contact::factory()->create();
    $product = Product::factory()->create();

    $quotation = $service->create([
        'contact_id' => $contact->id,
        'quotation_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'items' => [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100000],
        ],
    ]);

    expect($quotation)->toBeInstanceOf(Quotation::class);
    expect($quotation->items)->toHaveCount(1);
    expect($quotation->quotation_number)->not->toBeNull();
});

it('prevents converting draft quotation', function () {
    $service = app(QuotationService::class);
    $quotation = Quotation::factory()->draft()->create();

    expect(fn() => $service->convertToInvoice($quotation))
        ->toThrow(InvalidArgumentException::class);
});
```

---

## Related Documentation

- [ADR-0003: Service Layer Pattern](../08-adr/0003-service-layer-pattern.md)
- [Controller Pattern](../07-code-patterns/controller-pattern.md)
- [Data Model](./data-model.md)
