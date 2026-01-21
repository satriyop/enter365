# Strategy Patterns Reference

All strategy patterns in Enter365.

---

## Accounting Strategies

### Overview

| Strategy Type | Interface | Config Key |
|---------------|-----------|------------|
| COGS Recognition | `COGSRecognitionStrategy` | `cogs_recognition` |
| Inventory Accounting | `InventoryAccountingStrategy` | `inventory_method` |
| Manufacturing Cost | `ManufacturingCostStrategy` | `manufacturing_costing` |
| Return Accounting | `ReturnAccountingStrategy` | `return_accounting` |
| Period Closing | `ClosingStrategy` | `closing_strategy` |

---

## COGSRecognitionStrategy

**Location:** `app/Contracts/Accounting/Strategies/COGSRecognitionStrategy.php`

```php
interface COGSRecognitionStrategy
{
    public function onInvoicePost(Invoice $invoice): ?JournalEntry;
    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry;
    public function calculateCOGS(Invoice $invoice): int;
    public function getIdentifier(): string;
}
```

| Implementation | Identifier | Behavior |
|----------------|------------|----------|
| `COGSOnInvoiceStrategy` | `on_invoice` | COGS when invoice posted |
| `COGSOnDeliveryStrategy` | `on_delivery` | COGS when goods shipped |
| `ManualCOGSStrategy` | `manual` | No automatic COGS |

**Recommended:** `on_invoice` (matches revenue recognition)

---

## InventoryAccountingStrategy

**Location:** `app/Contracts/Accounting/Strategies/InventoryAccountingStrategy.php`

```php
interface InventoryAccountingStrategy
{
    public function onGoodsReceived(GoodsReceiptNote $grn): ?JournalEntry;
    public function onGoodsShipped(DeliveryOrder $deliveryOrder): ?JournalEntry;
    public function onStockAdjustment(StockOpname $stockOpname): ?JournalEntry;
    public function getIdentifier(): string;
}
```

| Implementation | Identifier | Behavior |
|----------------|------------|----------|
| `PerpetualInventoryStrategy` | `perpetual` | Journals on every movement |
| `PeriodicInventoryStrategy` | `periodic` | Journals at period end only |
| `HybridInventoryStrategy` | `hybrid` | Only stock opname journals |

**Recommended for EPC:** `hybrid`

---

## ManufacturingCostStrategy

**Location:** `app/Contracts/Accounting/Strategies/ManufacturingCostStrategy.php`

```php
interface ManufacturingCostStrategy
{
    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry;
    public function onMaterialConsumption(MaterialConsumption $consumption): ?JournalEntry;
    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry;
    public function calculateTotalCost(WorkOrder $workOrder): int;
    public function getIdentifier(): string;
}
```

| Implementation | Identifier | Behavior |
|----------------|------------|----------|
| `ProjectBasedCostingStrategy` | `project_based` | Costs flow to projects |
| `JobCostingStrategy` | `job_costing` | Costs per work order |
| `WIPAccountingStrategy` | `wip_accounting` | Full WIP journals |

**Recommended for EPC:** `project_based`

---

## ReturnAccountingStrategy

**Location:** `app/Contracts/Accounting/Strategies/ReturnAccountingStrategy.php`

```php
interface ReturnAccountingStrategy
{
    public function onSalesReturnApprove(SalesReturn $salesReturn): ?JournalEntry;
    public function onPurchaseReturnApprove(PurchaseReturn $purchaseReturn): ?JournalEntry;
    public function getIdentifier(): string;
}
```

| Implementation | Identifier | Behavior |
|----------------|------------|----------|
| `FullReturnJournalStrategy` | `full_journal` | Complete reversal journals |
| `InventoryOnlyReturnStrategy` | `inventory_only` | Only inventory updates |

---

## ClosingStrategy

**Location:** `app/Contracts/Accounting/Strategies/ClosingStrategy.php`

```php
interface ClosingStrategy
{
    public function closeRevenueAccounts(FiscalPeriod $period): ?JournalEntry;
    public function closeExpenseAccounts(FiscalPeriod $period): ?JournalEntry;
    public function closeIncomeSummary(FiscalPeriod $period): ?JournalEntry;
    public function closeDividends(FiscalPeriod $period): ?JournalEntry;
    public function getIdentifier(): string;
}
```

| Implementation | Identifier | Entries |
|----------------|------------|---------|
| `DirectClosingStrategy` | `direct` | 3 entries |
| `IncomeSummaryStrategy` | `income_summary` | 4 entries |

---

## AccountingPolicyManager

**Location:** `app/Services/Accounting/AccountingPolicyManager.php`

Central factory for resolving strategies:

```php
class AccountingPolicyManager
{
    public function inventory(): InventoryAccountingStrategy;
    public function cogs(): COGSRecognitionStrategy;
    public function returns(): ReturnAccountingStrategy;
    public function manufacturing(): ManufacturingCostStrategy;
    public function closing(): ClosingStrategy;
    public function getCurrentPolicies(): array;
}
```

---

## Configuration

**File:** `config/accounting.php`

```php
'policies' => [
    'inventory_method' => env('ACCOUNTING_INVENTORY_METHOD', 'hybrid'),
    'cogs_recognition' => env('ACCOUNTING_COGS_RECOGNITION', 'on_invoice'),
    'return_accounting' => env('ACCOUNTING_RETURN_METHOD', 'full_journal'),
    'manufacturing_costing' => env('ACCOUNTING_MFG_METHOD', 'project_based'),
    'closing_strategy' => env('ACCOUNTING_CLOSING_STRATEGY', 'direct'),
]
```

---

## Service Binding

**File:** `app/Providers/AppServiceProvider.php`

```php
$this->app->singleton(AccountingPolicyManager::class);

$this->app->bind(InventoryAccountingStrategy::class, function ($app) {
    return $app->make(AccountingPolicyManager::class)->inventory();
});

$this->app->bind(COGSRecognitionStrategy::class, function ($app) {
    return $app->make(AccountingPolicyManager::class)->cogs();
});
```

---

## Using Strategies in Services

```php
class InvoiceService
{
    public function __construct(
        private COGSRecognitionStrategy $cogsStrategy
    ) {}

    public function post(Invoice $invoice): ServiceResult
    {
        return $this->executeInTransaction('post', function () use ($invoice) {
            // Post invoice
            $this->journalService->postInvoice($invoice);

            // COGS via strategy
            $this->cogsStrategy->onInvoicePost($invoice);

            return ServiceResult::success(...);
        });
    }
}
```

---

## Creating New Strategy

### Step 1: Create Interface (if new type)

```php
// app/Contracts/YourModule/Strategies/YourStrategy.php
interface YourStrategy
{
    public function execute(Model $model): mixed;
    public function getIdentifier(): string;
}
```

### Step 2: Create Implementations

```php
// app/Services/YourModule/Strategies/OptionAStrategy.php
class OptionAStrategy implements YourStrategy
{
    public function execute(Model $model): mixed
    {
        // Implementation
    }

    public function getIdentifier(): string
    {
        return 'option_a';
    }
}
```

### Step 3: Add to Manager (or create new manager)

```php
private array $yourStrategies = [
    'option_a' => OptionAStrategy::class,
    'option_b' => OptionBStrategy::class,
];

public function your(): YourStrategy
{
    $method = config('your.strategy', 'option_a');
    return $this->container->make($this->yourStrategies[$method]);
}
```

### Step 4: Register Binding

```php
$this->app->bind(YourStrategy::class, function ($app) {
    return $app->make(YourPolicyManager::class)->your();
});
```

---

## Strategy Composition Pattern

### Problem: Strategies Creating Other Strategies

When strategies need shared behavior, **don't create strategies with `new`**:

```php
// ❌ BAD: Tight coupling via new
class COGSOnDeliveryStrategy implements COGSRecognitionStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function calculateCOGS(Invoice $invoice): int
    {
        // Creates dependency internally - can't mock, can't swap
        return (new COGSOnInvoiceStrategy($this->journalService))
            ->calculateCOGS($invoice);
    }
}
```

### Solution: Inject Dependent Strategies

Let Laravel's container resolve the dependency chain:

```php
// ✅ GOOD: Strategy composition via DI
class COGSOnDeliveryStrategy implements COGSRecognitionStrategy
{
    public function __construct(
        private COGSOnInvoiceStrategy $invoiceStrategy  // Injected!
    ) {}

    public function calculateCOGS(Invoice $invoice): int
    {
        return $this->invoiceStrategy->calculateCOGS($invoice);
    }
}
```

### Why This Works

1. **Laravel auto-resolves** - Container builds dependency graph automatically
2. **Testable** - Mock the injected strategy in tests
3. **Explicit dependencies** - Visible in constructor
4. **No unused properties** - Only inject what you actually use

### Real Examples (Fixed Jan 2026)

| Strategy | Injects | For Method |
|----------|---------|------------|
| `COGSOnDeliveryStrategy` | `COGSOnInvoiceStrategy` | `calculateCOGS()` |
| `PeriodicInventoryStrategy` | `HybridInventoryStrategy` | `onStockAdjustment()` |
| `PerpetualInventoryStrategy` | `HybridInventoryStrategy` | `onStockAdjustment()` |

### Detection

Run the CI script to catch violations:

```bash
./scripts/check-pattern-compliance.sh
# Checks for: new ...Strategy() in strategy files
```

Or manually:

```bash
grep -rn "new [A-Za-z]*Strategy(" app/Services/Accounting/Strategies/
```

---

## Other Strategies (Non-Accounting)

| Strategy | Interface | Purpose |
|----------|-----------|---------|
| Tax Calculation | `TaxCalculationStrategy` | Inclusive vs Exclusive |
| Pricing | `PricingStrategy` | Standard vs Margin-based |
| Approval | `ApprovalStrategy` | Auto vs Amount-based |
| Number Generation | `NumberGenerationStrategy` | Sequential vs Project-based |
