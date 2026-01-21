# Model Patterns

Eloquent model patterns and conventions.

---

## Model Organization

```
app/Models/
├── Sales/
│   ├── Invoice.php
│   ├── InvoiceItem.php
│   ├── Quotation.php
│   └── QuotationItem.php
├── Purchasing/
│   ├── Bill.php
│   └── PurchaseOrder.php
├── Manufacturing/
│   ├── WorkOrder.php
│   └── Bom.php
├── Inventory/
│   ├── Product.php
│   └── Warehouse.php
├── Accounting/
│   ├── JournalEntry.php
│   └── FiscalPeriod.php
├── Contacts/
│   └── Contact.php
└── Core/
    └── User.php
```

---

## Standard Traits

```php
use HasFactory;           // All models
use SoftDeletes;          // Documents (Invoice, Bill, etc.)
use Filterable;           // Queryable models
use HasStatusHistory;     // Status tracking
```

---

## Cast Patterns

```php
protected function casts(): array
{
    return [
        // Amounts (integers for precision)
        'unit_price' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',

        // Rates/Percentages
        'tax_rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'exchange_rate' => 'decimal:4',

        // Quantities
        'quantity' => 'decimal:4',

        // Booleans
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',

        // Dates
        'invoice_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',

        // Enums
        'status' => DocumentStatus::class,

        // JSON
        'custom_fields' => 'array',
    ];
}
```

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
