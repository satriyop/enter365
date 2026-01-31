# Enums Registry

All 11 enums in Enter365 across `app/Enums/` and domain-specific directories.

---

## Core Enum

### DocumentStatus

**Location:** `app/Enums/DocumentStatus.php`

**Used across all modules** for workflow status tracking.

```php
enum DocumentStatus: string
{
    // Universal
    case Draft = 'draft';
    case Cancelled = 'cancelled';

    // Approval workflow
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    // Completion
    case Completed = 'completed';
    case Converted = 'converted';
    case Expired = 'expired';

    // Financial
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Received = 'received';

    // Financial (advanced)
    case FullyApplied = 'fully_applied';
    case Refunded = 'refunded';

    // Manufacturing
    case Active = 'active';
    case Inactive = 'inactive';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Processing = 'processing';
    case Applied = 'applied';
    case Issued = 'issued';
    case Assigned = 'assigned';

    // Delivery
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Receiving = 'receiving';

    // Stock Opname
    case Counting = 'counting';
    case Reviewed = 'reviewed';

    // Project
    case Planning = 'planning';
    case OnHold = 'on_hold';

    // Solar/BOM
    case Accepted = 'accepted';
    case Archived = 'archived';

    public function label(): string;     // Indonesian label
    public function color(): string;     // Tailwind color
    public function isEditable(): bool;  // Only Draft
    public function isTerminal(): bool;  // No further transitions

    // Document-specific filters
    public static function forInvoice(): array;
    public static function forQuotation(): array;
    public static function forPurchaseOrder(): array;
    public static function forWorkOrder(): array;
    // ... etc
}
```

---

## Domain-Specific Enums

### FiscalPeriodStatus

**Location:** `app/Domain/Accounting/FiscalPeriods/Enums/FiscalPeriodStatus.php`

```php
enum FiscalPeriodStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Closing = 'closing';
    case Closed = 'closed';

    public function label(): string;
    public function color(): string;
    public function allowsTransactions(): bool;  // Only Open
    public function allowsPosting(): bool;       // Open or Locked
    public function isTerminal(): bool;          // Closed
}
```

### ClosingStep

**Location:** `app/Domain/Accounting/FiscalPeriods/Enums/ClosingStep.php`

```php
enum ClosingStep: string
{
    case LockPeriod = 'lock_period';
    case ValidateChecklist = 'validate_checklist';
    case CloseTemporaryAccounts = 'close_temporary_accounts';
    case CloseDividends = 'close_dividends';
    case MarkPeriodClosed = 'mark_period_closed';
    case CreateNextPeriod = 'create_next_period';
    case PopulateOpeningBalances = 'populate_opening_balances';

    public function label(): string;
    public function order(): int;              // 1-7
    public function isReversible(): bool;
    public function createsJournalEntry(): bool;
    public static function inOrder(): array;   // Sorted by order()
    public function next(): ?self;
}
```

### QuotationPriority

**Location:** `app/Domain/Sales/Quotations/Enums/QuotationPriority.php`

```php
enum QuotationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string;
    public function isHigh(): bool;  // High or Urgent

    public const ALL = ['low', 'normal', 'high', 'urgent'];
}
```

### QuotationType

**Location:** `app/Domain/Sales/Quotations/Enums/QuotationType.php`

```php
enum QuotationType: string
{
    case Single = 'single';
    case MultiOption = 'multi_option';

    public function label(): string;
}
```

### QuotationOutcome

**Location:** `app/Domain/Sales/Quotations/Enums/QuotationOutcome.php`

```php
enum QuotationOutcome: string
{
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function label(): string;

    public const WON_REASONS = [
        'harga_kompetitif' => 'Harga Kompetitif',
        'kualitas_produk' => 'Kualitas Produk',
        // ...
    ];

    public const LOST_REASONS = [
        'harga_tinggi' => 'Harga Terlalu Tinggi',
        'kalah_kompetitor' => 'Kalah dari Kompetitor',
        // ...
    ];
}
```

### BankTransactionStatus

**Location:** `app/Enums/BankTransactionStatus.php`

```php
enum BankTransactionStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Reconciled = 'reconciled';
}
```

### BudgetStatus

**Location:** `app/Enums/BudgetStatus.php`

```php
enum BudgetStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Closed = 'closed';
}
```

### MrpSuggestionStatus

**Location:** `app/Enums/MrpSuggestionStatus.php`

```php
enum MrpSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';
}
```

### WorkOrderType

**Location:** `app/Domain/Manufacturing/WorkOrders/Enums/WorkOrderType.php`

```php
enum WorkOrderType: string
{
    case Production = 'production';
    case Assembly = 'assembly';
    case Installation = 'installation';
    case Maintenance = 'maintenance';
}
```

### WorkOrderPriority

**Location:** `app/Domain/Manufacturing/WorkOrders/Enums/WorkOrderPriority.php`

```php
enum WorkOrderPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
```

---

## Enum Conventions

### Naming

| Type | Pattern | Example |
|------|---------|---------|
| Enum Name | TitleCase | `DocumentStatus` |
| Case Name | TitleCase | `InProgress` |
| Backing Value | snake_case | `'in_progress'` |

### Standard Methods

```php
public function label(): string      // Human-readable (Indonesian)
public function color(): string      // Tailwind color name
public function isX(): bool          // State checks
```

### Color Mapping

| Color | Statuses |
|-------|----------|
| `zinc` | Draft |
| `red` | Cancelled, Rejected |
| `yellow` | Submitted, Processing |
| `green` | Approved, Completed, Paid |
| `blue` | Converted, Active |
| `orange` | Expired, Overdue |
| `indigo` | Partial, InProgress |
| `cyan` | Sent, Shipped |
| `emerald` | Confirmed, Received |
| `gray` | Inactive, Archived |

---

## Using Enums

### In Model Cast

```php
protected function casts(): array
{
    return [
        'status' => DocumentStatus::class,
        'priority' => QuotationPriority::class,
    ];
}
```

### In Queries

```php
Invoice::where('status', DocumentStatus::Draft)->get();
Invoice::where('status', DocumentStatus::Draft->value)->get();
```

### In Conditions

```php
if ($invoice->status === DocumentStatus::Paid) {
    // ...
}

if ($invoice->status->isTerminal()) {
    // ...
}
```

### Display

```php
echo $invoice->status->label();  // "Lunas"
echo $invoice->status->color();  // "green"
```

---

## Creating New Enum

```php
<?php

namespace App\Domain\YourModule\Enums;

enum YourEnum: string
{
    case OptionA = 'option_a';
    case OptionB = 'option_b';
    case OptionC = 'option_c';

    public function label(): string
    {
        return match ($this) {
            self::OptionA => 'Opsi A',
            self::OptionB => 'Opsi B',
            self::OptionC => 'Opsi C',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OptionA => 'blue',
            self::OptionB => 'green',
            self::OptionC => 'yellow',
        };
    }

    public function isActive(): bool
    {
        return $this === self::OptionA || $this === self::OptionB;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn ($case) => [$case->value => $case->label()]
        )->toArray();
    }
}
```

### Location Rules

- **Core enums** (used across modules): `app/Enums/`
- **Domain-specific**: `app/Domain/{Module}/{Feature}/Enums/`
