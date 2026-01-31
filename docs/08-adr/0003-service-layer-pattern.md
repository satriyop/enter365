---
adr: "0003"
title: "Service Layer for Business Logic"
status: accepted
date: 2024-11-01
deciders: [Architecture Team]
tags: [architecture, patterns, structure]
related_adrs: [0001, 0005, 0010]
related_modules: [all]
impact: high
---

# ADR-0003: Service Layer for Business Logic

## AI Agent Quick Reference

**Use this ADR when:**
- Adding new business logic (create in service, not controller)
- Understanding where code should live
- Debugging business logic issues

**Key takeaway:** All business logic lives in `/app/Services/Accounting/`. Controllers are thin - they only handle HTTP concerns and delegate to services.

---

## Context

Enter365's accounting system has complex business logic including:
- Double-entry bookkeeping with balanced journals
- Multi-step document workflows (draft → approved → posted)
- MRP calculations with BOM explosion
- Tax calculations (PPN 11%)
- Multi-currency conversions

This logic needs to be:
- Testable in isolation
- Reusable across API and potential CLI/queue contexts
- Maintainable by separating concerns

---

## Decision Drivers

1. **Testability** - Business logic must be unit testable without HTTP
2. **Reusability** - Same logic may be called from API, CLI, queues
3. **Separation of Concerns** - HTTP handling separate from business rules
4. **Maintainability** - Clear location for business logic
5. **Transaction Management** - Complex operations need transaction wrapping

---

## Considered Options

### Option 1: Service Layer (Chosen)

**Description:** Dedicated service classes containing all business logic

**Pros:**
- Clear separation of concerns
- Easy to test without HTTP layer
- Reusable across contexts
- Transaction wrapping in one place
- Explicit dependencies via constructor injection

**Cons:**
- More files to maintain
- Some simple operations feel like overkill

### Option 2: Fat Controllers

**Description:** Business logic directly in controller methods

**Pros:**
- Fewer files
- Direct path from request to response

**Cons:**
- Hard to test business logic
- Logic not reusable
- Controllers become bloated
- Mixed concerns

### Option 3: Action Classes

**Description:** Single-purpose action classes (e.g., `CreateQuotation`)

**Pros:**
- Single Responsibility per class
- Very focused tests

**Cons:**
- Many more files
- Related operations scattered
- Harder to see module overview

### Option 4: Domain Model (Rich Models)

**Description:** Business logic in Eloquent models

**Pros:**
- Logic close to data
- Object-oriented approach

**Cons:**
- Models become bloated
- Hard to inject dependencies
- Mixed data access and business logic

---

## Decision

**Chosen option:** "Service Layer"

All business logic is implemented in service classes under `/app/Services/Accounting/`. Controllers are thin and only handle HTTP concerns.

---

## Rationale

### Why Service Layer:

1. **Clear Separation**
   - Controllers: HTTP handling, request validation, response formatting
   - Services: Business logic, transaction management, data operations
   - Models: Data access, relationships, scopes

2. **Testability**
   - Services can be instantiated directly in tests
   - Dependencies injected via constructor
   - No HTTP layer needed for unit tests

3. **Reusability**
   - Same service method callable from API, CLI, queued jobs
   - Example: `MrpService::execute()` callable from controller or queue

4. **Transaction Management**
   - Services wrap complex operations in `DB::transaction()`
   - Clear boundaries for atomic operations

5. **Maintainability**
   - All quotation logic in `QuotationService`
   - Easy to find and modify related operations

---

## Consequences

### Positive

- 39 focused service classes (vs. 53 controllers)
- Business logic testable without HTTP
- Clear code organization by domain
- Transaction boundaries explicit

### Negative

- More files than fat controllers
- Some indirection for simple operations
- Need to ensure services don't become god classes

### Neutral

- Team must understand where to put code
- Refactoring moves logic from controller to service

---

## Implementation Notes

**Directory Structure:**

```
app/Services/Accounting/
├── AccountBalanceService.php      # Balance calculations
├── AgingReportService.php         # AR/AP aging
├── BomService.php                 # Bill of Materials
├── BomTemplateService.php         # BOM templates
├── BomVariantGroupService.php     # BOM variants
├── BudgetService.php              # Budgeting
├── COGSReportService.php          # COGS reports
├── CashFlowReportService.php      # Cash flow
├── ComponentCrossReferenceService.php  # Component alternatives
├── DeliveryOrderService.php       # Delivery orders
├── DownPaymentService.php         # Down payments
├── FinancialReportService.php     # Financial statements
├── FiscalPeriodService.php        # Fiscal periods
├── GoodsReceiptNoteService.php    # GRN processing
├── InventoryService.php           # Stock movements
├── JournalService.php             # Journal entries
├── MaterialRequisitionService.php # Material requests
├── MrpService.php                 # MRP calculations
├── OverdueService.php             # Overdue detection
├── ProjectReportService.php       # Project reports
├── PurchaseOrderService.php       # Purchase orders
├── PurchaseReturnService.php      # Purchase returns
├── QuotationService.php           # Quotations
├── RecurringService.php           # Recurring docs
├── ReminderService.php            # Payment reminders
├── SalesReturnService.php         # Sales returns
├── SolarCalculationService.php    # Solar calculations
├── SolarProposalService.php       # Solar proposals
├── SpecValidationService.php      # Spec validation
├── StockOpnameService.php         # Physical inventory
├── SubcontractorReportService.php # Subcontractor reports
├── SubcontractorService.php       # Subcontractor work
├── TaxReportService.php           # Tax reports
├── WorkOrderReportService.php     # WO reports
└── WorkOrderService.php           # Work orders
```

**Service Pattern (Current Architecture):**

All services extend `BaseService` with composable traits:

```php
// File: /app/Services/Sales/QuotationService.php

namespace App\Services\Sales;

use App\Contracts\Services\Domains\QuotationServiceInterface;
use App\Models\Sales\Quotation;
use App\Services\Base\BaseService;

class QuotationService extends BaseService implements QuotationServiceInterface
{
    /**
     * Create a new quotation with items.
     *
     * @param array{
     *     contact_id: int,
     *     quotation_date: string,
     *     valid_until: string,
     *     items: array<array{product_id: int, quantity: float, unit_price: float}>
     * } $data
     */
    public function create(array $data): Quotation
    {
        // BaseService provides executeInTransaction() via WithTransaction trait
        return $this->executeInTransaction('create_quotation', function () use ($data) {
            // Generate number
            $data['quotation_number'] = $this->generateNumber();

            // Create quotation
            $quotation = Quotation::create($data);

            // Sync items
            $this->syncItems($quotation, $data['items'] ?? []);

            // Calculate totals
            $quotation->calculateTotals();

            return $quotation->fresh(['items', 'contact']);
        });
    }

    /**
     * Convert approved quotation to invoice.
     */
    public function convertToInvoice(Quotation $quotation, array $data = []): Invoice
    {
        if (!$quotation->canConvert()) {
            throw new InvalidArgumentException('Quotation cannot be converted.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $invoice = Invoice::create([
                'contact_id' => $quotation->contact_id,
                'quotation_id' => $quotation->id,
                // ... mapping
            ]);

            $quotation->update([
                'status' => Quotation::STATUS_CONVERTED,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }
}
```

**Controller Pattern (Thin):**

```php
// File: /app/Http/Controllers/Api/V1/QuotationController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Services\Accounting\QuotationService;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService
    ) {}

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        // Controller only handles HTTP concerns
        $quotation = $this->quotationService->create($request->validated());

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }

    public function convertToInvoice(Quotation $quotation): JsonResponse
    {
        try {
            $invoice = $this->quotationService->convertToInvoice($quotation);
            return response()->json([
                'message' => 'Penawaran berhasil dikonversi.',
                'invoice' => new InvoiceResource($invoice),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
```

**Key Patterns:**

1. **BaseService Extension** - All services extend `BaseService` for core functionality
2. **Composable Traits** - `WithTransaction`, `WithEventDispatching`, `WithOperationContext`, `WithDocuments`
3. **Transaction Wrapping** - `executeInTransaction()` for atomic operations with logging
4. **Constructor Injection** - Dependencies via constructor property promotion
5. **Method Documentation** - Array shape PHPDoc for complex parameters
6. **Return Fresh** - `->fresh(['relationships'])` for consistent responses
7. **Exception for Validation** - Throw exceptions for business rule violations
8. **Operation Context** - Automatic user/tenant tracking via `WithOperationContext` trait

---

## Validation

**Verification Steps:**

1. Check `/app/Services/Accounting/` has 39 service files
2. Check controllers delegate to services, not contain business logic
3. Check services use `DB::transaction()` for multi-step operations

**Test Example:**

```php
// File: /tests/Unit/Services/QuotationServiceTest.php

it('creates quotation with items', function () {
    $service = app(QuotationService::class);

    $quotation = $service->create([
        'contact_id' => Contact::factory()->create()->id,
        'quotation_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'items' => [
            ['product_id' => 1, 'quantity' => 10, 'unit_price' => 100000],
        ],
    ]);

    expect($quotation)->toBeInstanceOf(Quotation::class);
    expect($quotation->items)->toHaveCount(1);
});
```

---

## References

- [Laravel Service Container](https://laravel.com/docs/12.x/container)
- ADR-0001: Laravel Framework
- ADR-0005: Single Accounting Namespace
- `/docs/07-code-patterns/service-pattern.md`

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Backend Team
