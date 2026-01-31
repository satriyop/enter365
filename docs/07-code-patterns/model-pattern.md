---
pattern: model
title: "Model Pattern"
location: app/Models/Accounting/
tags: [architecture, models, eloquent]
---

# Model Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Creating new Eloquent models
- Defining relationships
- Adding accessors/mutators
- Understanding model conventions

**Key rule:** Models are thin - relationships, casts, and simple accessors only. No business logic.

---

## Model Structure

```php
<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Traits\Auditable;
use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Auditable;
    use HasAttachments;

    // ─────────────────────────────────────────────────────────────
    // Configuration
    // ─────────────────────────────────────────────────────────────

    protected $table = 'invoices';

    protected $fillable = [
        'contact_id',
        'number',
        'date',
        'due_date',
        'reference',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',  // Contract standard: use total_amount
        'amount_paid',
        'amount_due',
        'notes',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',  // Contract standard: use total_amount
            'amount_paid' => 'integer',
            'amount_due' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) {
            return 0;
        }

        return $this->due_date->diffInDays(now());
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return Currency::format($this->subtotal);
    }

    public function getTotalFormattedAttribute(): string
    {
        return Currency::format($this->total);
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('amount_due', '>', 0);
    }

    public function scopeOverdue($query)
    {
        return $query->unpaid()
            ->where('due_date', '<', now());
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeFilter($query, array $filters)
    {
        return (new InvoiceFilter($query, $filters))->apply();
    }

    // ─────────────────────────────────────────────────────────────
    // Simple State Checks (not business logic)
    // ─────────────────────────────────────────────────────────────

    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    public function canDelete(): bool
    {
        return $this->status === 'draft';
    }

    public function canApprove(): bool
    {
        return $this->status === 'draft' && $this->items->isNotEmpty();
    }
}
```

---

## Key Principles

### 1. All Models in Accounting Namespace

```php
namespace App\Models\Accounting;

// NOT App\Models\Invoice
```

### 2. Use casts() Method (Laravel 11+)

```php
protected function casts(): array
{
    return [
        'date' => 'date',
        'total' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
```

### 3. Typed Relationships

```php
public function contact(): BelongsTo
{
    return $this->belongsTo(Contact::class);
}

public function items(): HasMany
{
    return $this->hasMany(InvoiceItem::class);
}
```

### 4. Scopes for Common Queries

```php
// Usage: Invoice::unpaid()->overdue()->get()
public function scopeUnpaid($query) { }
public function scopeOverdue($query) { }
```

---

## What Belongs in Models

| ✓ Include | ✗ Exclude |
|-----------|-----------|
| Relationships | Create/update logic |
| Casts | Journal entries |
| Accessors (computed) | Sending emails |
| Scopes | Calculations |
| Simple state checks | External API calls |
| Fillable/guarded | Multi-model operations |

---

## Common Traits

| Trait | Purpose |
|-------|---------|
| `HasFactory` | Test factories |
| `SoftDeletes` | Soft delete support |
| `Auditable` | Activity logging |
| `HasAttachments` | File attachments |
| `BelongsToTenant` | Multi-tenancy (future) |

---

## Integer Money Storage

```php
// Store as integer (cents/smallest unit)
protected function casts(): array
{
    return [
        'subtotal' => 'integer',        // 1500000 = Rp 1,500,000
        'tax_amount' => 'integer',
        'total_amount' => 'integer',    // Contract standard: use total_amount
    ];
}

// Format for display via accessor
public function getTotalAmountFormattedAttribute(): string
{
    return Currency::format($this->total_amount);
}
```

---

## Related Documents

- [ADR-0005: Single Accounting Namespace](../08-adr/0005-single-accounting-namespace.md)
- [ADR-0008: Integer Currency Storage](../08-adr/0008-integer-currency-storage.md)
- [Data Model](../01-architecture/data-model.md)
- [Service Pattern](./service-pattern.md)

