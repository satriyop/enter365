# Model Patterns

72 Eloquent models across 11 directories. Patterns and conventions.

---

## Model Organization

```
app/Models/
├── User.php
├── CompanyProfile.php
├── Accounting/          (10 models)
│   ├── Account.php, BankTransaction.php, Budget.php
│   ├── BudgetLine.php, Currency.php, ExchangeRate.php
│   ├── FiscalPeriod.php, JournalEntry.php, JournalEntryLine.php
├── Contacts/            (1 model)
│   └── Contact.php
├── Core/                (4 models)
│   ├── AuditLog.php, Permission.php, Role.php, StatusHistory.php
├── Inventory/           (7 models)
│   ├── InventoryMovement.php, Product.php, ProductCategory.php
│   ├── ProductStock.php, StockOpname.php, StockOpnameItem.php
│   └── Warehouse.php
├── Manufacturing/       (18 models)
│   ├── Bom.php, BomItem.php, BomTemplate.php, BomTemplateItem.php
│   ├── BomVariantGroup.php, ComponentBrandMapping.php
│   ├── ComponentStandard.php, MaterialConsumption.php
│   ├── MaterialRequisition.php, MaterialRequisitionItem.php
│   ├── MrpDemand.php, MrpRun.php, MrpSuggestion.php
│   ├── SpecValidationRule.php, SpecValidationRuleSet.php
│   ├── SubcontractorWorkOrder.php, WorkOrder.php, WorkOrderItem.php
├── Projects/            (3 models)
│   ├── Project.php, ProjectCost.php, ProjectRevenue.php
├── Purchasing/          (8 models)
│   ├── Bill.php, BillItem.php, GoodsReceiptNote.php
│   ├── GoodsReceiptNoteItem.php, PurchaseOrder.php
│   ├── PurchaseOrderItem.php, PurchaseReturn.php, PurchaseReturnItem.php
├── Sales/               (12 models)
│   ├── DeliveryOrder.php, DeliveryOrderItem.php
│   ├── DownPayment.php, DownPaymentApplication.php
│   ├── Invoice.php, InvoiceItem.php, Quotation.php
│   ├── QuotationActivity.php, QuotationItem.php
│   ├── QuotationVariantOption.php, SalesReturn.php, SalesReturnItem.php
├── Shared/              (5 models)
│   ├── Attachment.php, Payment.php, PaymentReminder.php
│   ├── RecurringTemplate.php, SubcontractorInvoice.php
└── Solar/               (3 models)
    ├── IndonesiaSolarData.php, PlnTariff.php, SolarProposal.php
```

---

## Standard Traits

| Trait | Count | Used On |
|-------|-------|---------|
| `HasFactory` | 65 | Almost all (except AuditLog, Currency, ExchangeRate, IndonesiaSolarData, PlnTariff, CompanyProfile, StatusHistory) |
| `SoftDeletes` | 29 | Documents, master data (Product, Contact, Warehouse, etc.) |
| `Filterable` | 27 | Models exposed via API with search/filter support |
| `HasStatusHistory` | 9 | WorkOrder, Project, Bill, PurchaseOrder, PurchaseReturn, DeliveryOrder, Invoice, Quotation, SalesReturn |
| `HasActiveStatus` | 7 | CompanyProfile, Product, ProductCategory, Warehouse, BomTemplate, ComponentStandard, SpecValidationRuleSet |
| `HasDocumentDiscount` | 5 | Bill, PurchaseOrder, Invoice, Quotation, RecurringTemplate |
| `HasRolesAndPermissions` | 1 | User |
| `HasApiTokens` | 1 | User (Sanctum) |

```php
// Typical document model
use Filterable, HasFactory, HasStatusHistory, SoftDeletes;

// Typical line item model
use HasFactory;

// Typical master data model
use Filterable, HasActiveStatus, HasFactory, SoftDeletes;
```

---

## Cast Patterns

**Laravel 12 uses `casts()` method (not `$casts` property):**

```php
protected function casts(): array
{
    return [
        // Amounts (integers for precision - stored as cents/smallest unit)
        'unit_price' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'discount_amount' => 'integer',
        'paid_amount' => 'integer',

        // Rates/Percentages (decimal strings)
        'tax_rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'early_discount_percent' => 'decimal:2',

        // Quantities
        'quantity' => 'decimal:4',

        // Booleans
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',

        // Dates
        'invoice_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'last_reminder_at' => 'datetime',

        // Enums
        'status' => DocumentStatus::class,

        // JSON
        'custom_fields' => 'array',
    ];
}
```

**Note:** Always use `protected function casts(): array` method, not `protected $casts = []` property. This is the Laravel 12 convention.

---

## Relationship Patterns

### HasMany (Parent → Children)

```php
public function items(): HasMany
{
    return $this->hasMany(InvoiceItem::class);
}
```

### BelongsTo (Child → Parent)

```php
public function invoice(): BelongsTo
{
    return $this->belongsTo(Invoice::class);
}

// Custom foreign key
public function revenueAccount(): BelongsTo
{
    return $this->belongsTo(Account::class, 'revenue_account_id');
}
```

### Self-Referential (Hierarchy)

```php
public function parent(): BelongsTo
{
    return $this->belongsTo(Account::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(Account::class, 'parent_id');
}
```

### Polymorphic

```php
// One-to-Many Polymorphic
public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable');
}

// In Attachment model
public function attachable(): MorphTo
{
    return $this->morphTo();
}
```

---

## Scope Patterns

```php
// Status-based
public function scopeDraft(Builder $query): Builder
{
    return $query->where('status', DocumentStatus::Draft);
}

public function scopeActive(Builder $query): Builder
{
    return $query->whereNotIn('status', [
        DocumentStatus::Cancelled,
        DocumentStatus::Rejected,
    ]);
}

// Boolean flags
public function scopeSellable(Builder $query): Builder
{
    return $query->where('is_sellable', true)
        ->where('is_active', true);
}

// Inventory
public function scopeLowStock(Builder $query): Builder
{
    return $query->where('track_inventory', true)
        ->whereColumn('current_stock', '<=', 'min_stock');
}
```

---

## Accessor Patterns

```php
// Computed property
public function getTotalStockAttribute(): int
{
    return $this->stocks()->sum('quantity');
}

// Business logic
public function isLowStock(): bool
{
    return $this->track_inventory && $this->current_stock <= $this->min_stock;
}

public function isOutOfStock(): bool
{
    return $this->track_inventory && $this->current_stock <= 0;
}

// State checks
public function canEdit(): bool
{
    return $this->status === DocumentStatus::Draft;
}

public function canSubmit(): bool
{
    return $this->status === DocumentStatus::Draft
        && $this->items()->exists();
}
```

---

## Number Generation

```php
public static function generateInvoiceNumber(): string
{
    $prefix = 'INV-'.now()->format('Ym').'-';

    $last = static::query()
        ->where('invoice_number', 'like', $prefix.'%')
        ->orderByDesc('invoice_number')
        ->first();

    if ($last) {
        $lastNumber = (int) substr($last->invoice_number, -4);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}
```

---

## Model Template

```php
<?php

namespace App\Models\YourModule;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Traits\Filterable;
use App\Traits\HasStatusHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class YourModel extends Model
{
    use Filterable, HasFactory, HasStatusHistory, SoftDeletes;

    protected $fillable = [
        'number',
        'name',
        'status',
        'contact_id',
        'total_amount',
        'tax_rate',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'status' => DocumentStatus::class,
        ];
    }

    // Relationships
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(YourModelItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Draft);
    }

    // Business logic
    public function canEdit(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function canSubmit(): bool
    {
        return $this->status === DocumentStatus::Draft
            && $this->items()->exists();
    }
}
```

---

## Field Naming Conventions

| Type | Pattern | Examples |
|------|---------|----------|
| Foreign Key | `{model}_id` | `contact_id`, `product_id` |
| User Reference | `{action}_by` | `created_by`, `approved_by` |
| Timestamp | `{action}_at` | `submitted_at`, `approved_at` |
| Amount | Integer in smallest unit | `total_amount`, `unit_price` |
| Quantity | Decimal:4 | `quantity`, `quantity_received` |
| Rate | Decimal:2 | `tax_rate`, `discount_percent` |
| Boolean | `is_{flag}` | `is_active`, `is_taxable` |
| Status | `status` | Cast to DocumentStatus |

---

## Common Relationships

| Model | Relationship | Related Model |
|-------|--------------|---------------|
| Invoice | `items()` | InvoiceItem |
| Invoice | `contact()` | Contact |
| Invoice | `payments()` | Payment |
| Invoice | `journalEntry()` | JournalEntry |
| InvoiceItem | `product()` | Product |
| InvoiceItem | `invoice()` | Invoice |
| Contact | `invoices()` | Invoice |
| Contact | `quotations()` | Quotation |
| Product | `stocks()` | ProductStock |
| Product | `category()` | ProductCategory |
