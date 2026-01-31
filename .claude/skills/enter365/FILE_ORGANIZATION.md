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
│   └── Listeners/
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

---

## CI Scripts

**Location:** `scripts/`

Scripts for CI/CD and development workflow:

| Script | Purpose | When to Run |
|--------|---------|-------------|
| `check-auth-id-usage.sh` | Detect `auth()->id()` in services | Before commit |
| `check-pattern-compliance.sh` | Full pattern compliance check | Before commit, CI |

### Pattern Compliance Script

```bash
./scripts/check-pattern-compliance.sh
```

Checks for:
1. `auth()->id()` in Services (use `$this->getUserId()`)
2. `new ...Strategy()` in Strategies (use constructor injection)
3. `??= app()` fallback pattern (use explicit DI)
4. Raw `DB::transaction()` in Pattern A services (use `executeInTransaction()`)

**Exit codes:**
- `0` - All checks passed
- `1` - Errors found (blocks CI)

**Warnings** (e.g., raw `DB::transaction()`) are advisory and don't block.

### Adding New Compliance Checks

Add new checks to `scripts/check-pattern-compliance.sh`:

```bash
# Example: Check for direct model creation in controllers
echo "Checking for Model::create() in controllers..."
VIOLATIONS=$(grep -rn "::create(" app/Http/Controllers/ || true)
if [ -n "$VIOLATIONS" ]; then
    echo "WARNING: Found direct model creation in controllers"
    # ...
fi
```
