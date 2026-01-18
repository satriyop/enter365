# Files Organization Pattern

This skill defines the mandatory directory organization patterns for this project. **ALWAYS follow these patterns** when creating new files or reorganizing code.

---

## Contracts Directory Structure

Contracts (interfaces) are organized by **domain**, mirroring the Services layer structure.

```
app/Contracts/
├── Accounting/
│   ├── AccountLookupServiceInterface.php
│   ├── JournalServiceInterface.php
│   └── Strategies/
├── Events/
│   └── EventDispatcherInterface.php
├── Inventory/
│   ├── InventoryServiceInterface.php
│   ├── ProductServiceInterface.php
│   └── StockOpnameServiceInterface.php
├── Manufacturing/
│   ├── BomServiceInterface.php
│   ├── BomTemplateServiceInterface.php
│   ├── BomVariantGroupServiceInterface.php
│   ├── MaterialRequisitionServiceInterface.php
│   ├── MrpServiceInterface.php
│   ├── SubcontractorServiceInterface.php
│   └── WorkOrderServiceInterface.php
├── Projects/
│   └── ProjectServiceInterface.php
├── Purchasing/
│   ├── BillServiceInterface.php
│   ├── GoodsReceiptNoteServiceInterface.php
│   ├── PurchaseOrderCalculatorInterface.php
│   ├── PurchaseOrderServiceInterface.php
│   └── PurchaseReturnServiceInterface.php
├── Sales/
│   ├── DeliveryOrderServiceInterface.php
│   ├── DownPaymentServiceInterface.php
│   ├── InvoiceCalculatorInterface.php
│   ├── InvoiceServiceInterface.php
│   ├── QuotationCalculatorInterface.php
│   ├── QuotationNumberGeneratorInterface.php
│   ├── QuotationServiceInterface.php
│   ├── RecurringServiceInterface.php
│   └── SalesReturnServiceInterface.php
├── Shared/
│   ├── DocumentLifecycleInterface.php
│   ├── DocumentNumberGeneratorInterface.php
│   ├── FinancialCalculationInterface.php
│   └── PaymentServiceInterface.php
├── Solar/
│   ├── SolarCalculationServiceInterface.php
│   └── SolarProposalServiceInterface.php
└── FeatureManager.php
```

### Rules for New Interfaces

1. **Domain-specific interfaces** → `App\Contracts\{Domain}\{Interface}.php`
   - Example: `App\Contracts\Sales\InvoiceServiceInterface`

2. **Shared/cross-cutting interfaces** → `App\Contracts\Shared\{Interface}.php`
   - Example: `App\Contracts\Shared\DocumentLifecycleInterface`

3. **NEVER** create `App\Contracts\Services\` - this is deprecated

---

## Domain Events & Handlers

Domain events and handler interfaces live **inside their domain module**, not in a global location.

### Events Location

```
app/Domain/{Module}/Events/           # Domain-level events
app/Domain/{Module}/{SubModule}/Events/  # Sub-module events
```

**Examples:**
- `App\Domain\Sales\Events\InvoicePosted`
- `App\Domain\Sales\SalesReturns\Events\SalesReturnApproved`
- `App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCompleted`

### Handler Interfaces Location

Handler interfaces (for pipelines) belong in the domain's Contracts folder:

```
app/Domain/{Module}/{SubModule}/Contracts/
```

**Examples:**
- `App\Domain\Sales\SalesReturns\Contracts\ApprovalHandlerInterface`
- `App\Domain\Purchasing\PurchaseReturns\Contracts\ApprovalHandlerInterface`

### Rules

1. **NEVER** put domain events in `app/Events/` - use `app/Domain/{Module}/Events/`
2. **NEVER** put handler interfaces in `app/Contracts/Handlers/` - use domain-specific `Contracts/` folder
3. Infrastructure listeners (logging, notifications) stay in `app/Infrastructure/Listeners/`

---

## Namespace Mapping Reference

| Domain | Namespace |
|--------|-----------|
| Accounting | `App\Contracts\Accounting\` |
| Inventory | `App\Contracts\Inventory\` |
| Manufacturing | `App\Contracts\Manufacturing\` |
| Projects | `App\Contracts\Projects\` |
| Purchasing | `App\Contracts\Purchasing\` |
| Sales | `App\Contracts\Sales\` |
| Solar | `App\Contracts\Solar\` |
| Shared | `App\Contracts\Shared\` |

---

## Quick Decision Guide

**Creating a new service interface?**
→ `App\Contracts\{Domain}\{Service}Interface.php`

**Creating a domain event?**
→ `App\Domain\{Module}\Events\{Event}.php`

**Creating a handler interface for a pipeline?**
→ `App\Domain\{Module}\{SubModule}\Contracts\{Handler}Interface.php`

**Creating a shared/reusable interface?**
→ `App\Contracts\Shared\{Interface}.php`
