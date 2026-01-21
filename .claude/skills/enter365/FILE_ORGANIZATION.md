# File Organization Patterns

Quick reference for where to put new files in Enter365.

---

## Directory Structure

```
app/
├── Contracts/                 # Interface definitions (DIP)
│   └── {Module}/
│       ├── {Feature}ServiceInterface.php
│       ├── {Feature}CalculatorInterface.php
│       └── Strategies/
├── Domain/                    # Pure business logic (no Laravel)
│   └── {Module}/
│       └── {Aggregate}/
│           ├── {Aggregate}StateMachine.php
│           ├── {Aggregate}Calculator.php
│           ├── {Aggregate}Defaults.php
│           ├── Events/
│           ├── Handlers/
│           └── ValueObjects/
├── Models/                    # Eloquent Models
│   └── {Module}/
├── Services/                  # Application services
│   └── {Module}/
│       ├── {Feature}Service.php
│       └── Strategies/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/Api/V1/
│   └── Resources/Api/V1/
├── Filters/                   # Query filters
├── Enums/                     # Core enums (DocumentStatus)
├── Infrastructure/
│   ├── Events/
│   ├── Listeners/
│   └── Repositories/
└── Providers/
```

---

## Module Names

| Module | Purpose |
|--------|---------|
| `Sales` | Quotations, Invoices, Delivery Orders, Sales Returns |
| `Purchasing` | Purchase Orders, Bills, GRN, Purchase Returns |
| `Manufacturing` | Work Orders, BOMs, Material Requisitions, MRP |
| `Inventory` | Products, Stock, Stock Opname, Warehouses |
| `Accounting` | Fiscal Periods, Journal Entries, Accounts |
| `Projects` | Project management |
| `Contacts` | Customers and Suppliers |
| `Shared` | Cross-cutting: Payments, Attachments, Number Generation |
| `Core` | Users, Roles, Permissions |

---

## Where to Create New Files

### New Feature (e.g., "SalesReceipt")

| Layer | Path | File |
|-------|------|------|
| Contract | `app/Contracts/Sales/` | `SalesReceiptServiceInterface.php` |
| Domain | `app/Domain/Sales/SalesReceipts/` | `SalesReceiptStateMachine.php` |
| Domain | `app/Domain/Sales/SalesReceipts/` | `SalesReceiptCalculator.php` |
| Domain Events | `app/Domain/Sales/SalesReceipts/Events/` | `SalesReceiptApproved.php` |
| Model | `app/Models/Sales/` | `SalesReceipt.php` |
| Model | `app/Models/Sales/` | `SalesReceiptItem.php` |
| Service | `app/Services/Sales/` | `SalesReceiptService.php` |
| Repository | `app/Infrastructure/Repositories/Sales/` | `EloquentSalesReceiptRepository.php` |
| Listener | `app/Listeners/Sales/` | `SalesReceiptEventSubscriber.php` |
| Controller | `app/Http/Controllers/Api/V1/` | `SalesReceiptController.php` |
| Request | `app/Http/Requests/Api/V1/` | `StoreSalesReceiptRequest.php` |
| Resource | `app/Http/Resources/Api/V1/` | `SalesReceiptResource.php` |
| Filter | `app/Filters/` | `SalesReceiptFilter.php` |

---

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Model | Singular noun | `Invoice`, `Product` |
| Model (Item) | `{Parent}Item` | `InvoiceItem` |
| Controller | `{Entity}Controller` | `InvoiceController` |
| Service | `{Entity}Service` | `InvoiceService` |
| Interface | `{Entity}ServiceInterface` | `InvoiceServiceInterface` |
| Repository | `Eloquent{Entity}Repository` | `EloquentInvoiceRepository` |
| Form Request | `Store{Entity}Request` | `StoreInvoiceRequest` |
| Form Request | `Update{Entity}Request` | `UpdateInvoiceRequest` |
| Resource | `{Entity}Resource` | `InvoiceResource` |
| Filter | `{Entity}Filter` | `InvoiceFilter` |
| State Machine | `{Entity}StateMachine` | `InvoiceStateMachine` |
| Event | `{Entity}{Action}` | `InvoiceApproved` |
| Listener | `{Entity}EventSubscriber` | `InvoiceEventSubscriber` |
| Calculator | `{Entity}Calculator` | `InvoiceCalculator` |

---

## Method Naming

| Pattern | Usage | Example |
|---------|-------|---------|
| `get{Property}()` | Computed property | `getFullNumber()` |
| `is{State}()` | Boolean check | `isExpired()` |
| `can{Action}()` | Permission check | `canApprove()` |
| `scope{Name}()` | Query scope | `scopeActive()` |
| `handle()` | Event listener | Always `handle()` |

---

## Quick Commands

```bash
# Create model with migration and factory
php artisan make:model Sales/SalesReceipt -mf --no-interaction

# Create controller
php artisan make:controller Api/V1/SalesReceiptController --api --no-interaction

# Create form request
php artisan make:request Api/V1/StoreSalesReceiptRequest --no-interaction

# Create resource
php artisan make:resource Api/V1/SalesReceiptResource --no-interaction
```

---

## Domain-Specific Enums

Place domain-specific enums in:
```
app/Domain/{Module}/{Feature}/Enums/{EnumName}.php
```

Example: `app/Domain/Sales/Quotations/Enums/QuotationPriority.php`

Core enums (used across modules) go in:
```
app/Enums/DocumentStatus.php
```
