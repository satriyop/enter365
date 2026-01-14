---
section: architecture
title: "Data Model"
order: 4
---

# Data Model

> **70 Eloquent models organized in a single namespace**
>
> All models live in `/app/Models/Accounting/` reflecting the interconnected nature of accounting data.

---

## AI Agent Quick Reference

**Use this document when:**
- Finding which model handles specific data
- Understanding entity relationships
- Creating new models
- Debugging relationship issues

**Key takeaway:** All models are in `App\Models\Accounting\`. They are interconnected across traditional "domain" boundaries.

---

## Model Overview by Category

### Core Accounting (9 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Account` | accounts | Chart of accounts |
| `JournalEntry` | journal_entries | Journal entry headers |
| `JournalEntryLine` | journal_entry_lines | Journal entry lines |
| `FiscalPeriod` | fiscal_periods | Accounting periods |
| `Currency` | currencies | Multi-currency support |
| `ExchangeRate` | exchange_rates | Historical rates |
| `Budget` | budgets | Budget headers |
| `BudgetLine` | budget_lines | Budget line items |
| `AuditLog` | audit_logs | Activity logging |

### Contacts (1 Model)

| Model | Table | Purpose |
|-------|-------|---------|
| `Contact` | contacts | Customers & vendors |

### Products (4 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Product` | products | Products & services |
| `ProductCategory` | product_categories | Category hierarchy |
| `ProductStock` | product_stocks | Stock per warehouse |
| `Attachment` | attachments | File attachments |

### Sales (10 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Quotation` | quotations | Sales quotations |
| `QuotationItem` | quotation_items | Quotation line items |
| `QuotationVariantOption` | quotation_variant_options | Multi-option quotes |
| `QuotationActivity` | quotation_activities | Follow-up tracking |
| `Invoice` | invoices | Sales invoices |
| `InvoiceItem` | invoice_items | Invoice line items |
| `DeliveryOrder` | delivery_orders | Shipping documents |
| `DeliveryOrderItem` | delivery_order_items | DO line items |
| `SalesReturn` | sales_returns | Customer returns |
| `SalesReturnItem` | sales_return_items | Return line items |

### Purchasing (8 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `PurchaseOrder` | purchase_orders | Purchase orders |
| `PurchaseOrderItem` | purchase_order_items | PO line items |
| `GoodsReceiptNote` | goods_receipt_notes | Receiving documents |
| `GoodsReceiptNoteItem` | goods_receipt_note_items | GRN line items |
| `Bill` | bills | Vendor bills |
| `BillItem` | bill_items | Bill line items |
| `PurchaseReturn` | purchase_returns | Vendor returns |
| `PurchaseReturnItem` | purchase_return_items | Return line items |

### Payments (4 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Payment` | payments | Payment records |
| `DownPayment` | down_payments | Down payment tracking |
| `DownPaymentApplication` | down_payment_applications | DP application to invoices |
| `PaymentReminder` | payment_reminders | Payment reminder log |

### Inventory (4 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Warehouse` | warehouses | Storage locations |
| `InventoryMovement` | inventory_movements | Stock movements |
| `StockOpname` | stock_opnames | Physical counts |
| `StockOpnameItem` | stock_opname_items | Count line items |

### Manufacturing (14 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Bom` | boms | Bills of material |
| `BomItem` | bom_items | BOM components |
| `BomTemplate` | bom_templates | Reusable templates |
| `BomTemplateItem` | bom_template_items | Template components |
| `BomVariantGroup` | bom_variant_groups | Multi-brand groups |
| `WorkOrder` | work_orders | Production orders |
| `WorkOrderItem` | work_order_items | WO line items |
| `MaterialRequisition` | material_requisitions | Material requests |
| `MaterialRequisitionItem` | material_requisition_items | Request line items |
| `MaterialConsumption` | material_consumptions | Consumption tracking |
| `MrpRun` | mrp_runs | MRP calculation runs |
| `MrpDemand` | mrp_demands | Demand records |
| `MrpSuggestion` | mrp_suggestions | Purchase suggestions |
| `ComponentStandard` | component_standards | Generic components |
| `ComponentBrandMapping` | component_brand_mappings | Brand alternatives |

### Projects (5 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Project` | projects | Project headers |
| `ProjectCost` | project_costs | Cost allocation |
| `ProjectRevenue` | project_revenues | Revenue tracking |
| `SubcontractorWorkOrder` | subcontractor_work_orders | Subcontracted work |
| `SubcontractorInvoice` | subcontractor_invoices | Subcontractor billing |

### Solar EPC (4 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `SolarProposal` | solar_proposals | Solar project proposals |
| `PlnTariff` | pln_tariffs | PLN electricity rates |
| `IndonesiaSolarData` | indonesia_solar_data | Solar irradiance data |
| `SpecValidationRuleSet` | spec_validation_rule_sets | Component validation |
| `SpecValidationRule` | spec_validation_rules | Validation rules |

### RBAC (3 Models)

| Model | Table | Purpose |
|-------|-------|---------|
| `Role` | roles | User roles |
| `Permission` | permissions | Permissions |
| `RecurringTemplate` | recurring_templates | Recurring doc templates |
| `BankTransaction` | bank_transactions | Bank statement import |

---

## Key Relationships

### Journal Entry (Core Accounting)

```
JournalEntry (1) ←→ (N) JournalEntryLine
      │
      └─ source (polymorphic: Invoice, Bill, Payment, etc.)
```

```php
// JournalEntry.php
public function lines(): HasMany
{
    return $this->hasMany(JournalEntryLine::class);
}

public function source(): MorphTo
{
    return $this->morphTo();
}
```

### Sales Document Chain

```
Contact (1) ←→ (N) Quotation (1) ←→ (N) QuotationItem
                      │
                      └─ converts to ──▶ Invoice (1) ←→ (N) InvoiceItem
                                              │
                                              ├─ (N) DeliveryOrder
                                              ├─ (N) Payment
                                              └─ (1) JournalEntry
```

### Manufacturing Chain

```
Product (finished) ←── Bom (1) ←→ (N) BomItem ──→ Product (component)
                         │
                         └─ BomVariantGroup (groups multiple BOMs)

Bom ──→ WorkOrder (1) ←→ (N) WorkOrderItem
              │
              └─ MaterialRequisition ──→ InventoryMovement
```

### Inventory Relationships

```
Product (1) ←→ (N) ProductStock ←→ (1) Warehouse
                        │
                        └─ updated by InventoryMovement
```

---

## Common Model Patterns

### Base Structure

```php
// File: /app/Models/Accounting/Invoice.php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'contact_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        // ...
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'tax_rate' => 'decimal:2',
        ];
    }
}
```

### Relationships

```php
// Belongs To
public function contact(): BelongsTo
{
    return $this->belongsTo(Contact::class);
}

// Has Many
public function items(): HasMany
{
    return $this->hasMany(InvoiceItem::class);
}

// Morph One
public function journalEntry(): MorphOne
{
    return $this->morphOne(JournalEntry::class, 'source');
}

// Belongs To Many
public function projects(): BelongsToMany
{
    return $this->belongsToMany(Project::class);
}
```

### Scopes

```php
// Status scopes
public function scopeDraft(Builder $query): Builder
{
    return $query->where('status', self::STATUS_DRAFT);
}

public function scopePosted(Builder $query): Builder
{
    return $query->where('status', self::STATUS_POSTED);
}

public function scopeOverdue(Builder $query): Builder
{
    return $query->where('due_date', '<', now())
        ->whereNull('paid_at');
}
```

### Calculated Attributes

```php
public function getBalanceAttribute(): int
{
    return $this->total - $this->payments->sum('amount');
}

public function getIsPaidAttribute(): bool
{
    return $this->balance <= 0;
}

public function getFormattedTotalAttribute(): string
{
    return 'Rp ' . number_format($this->total / 100, 2, ',', '.');
}
```

---

## Entity Relationship Diagram (Simplified)

```
┌─────────────┐     ┌─────────────────┐     ┌───────────────┐
│   Contact   │     │    Product      │     │   Warehouse   │
│             │     │                 │     │               │
│ - customers │     │ - sku           │     │ - name        │
│ - vendors   │     │ - name          │     │ - location    │
└──────┬──────┘     │ - type          │     └───────┬───────┘
       │            └────────┬────────┘             │
       │                     │                      │
       │    ┌────────────────┼──────────────────────┤
       │    │                │                      │
       ▼    ▼                ▼                      ▼
┌──────────────┐     ┌─────────────┐      ┌──────────────────┐
│  Quotation   │     │     Bom     │      │   ProductStock   │
│              │     │             │      │                  │
│ - items      │     │ - items     │      │ - quantity       │
│ - total      │     │ - cost      │      │ - warehouse_id   │
└──────┬───────┘     └──────┬──────┘      │ - product_id     │
       │                    │             └──────────────────┘
       ▼                    ▼
┌──────────────┐     ┌─────────────┐
│   Invoice    │     │  WorkOrder  │
│              │     │             │
│ - items      │     │ - items     │
│ - payments   │     │ - status    │
└──────┬───────┘     └─────────────┘
       │
       ▼
┌──────────────┐     ┌───────────────────┐
│   Payment    │────▶│   JournalEntry    │
│              │     │                   │
│ - amount     │     │ - lines (D/C)     │
└──────────────┘     │ - balanced        │
                     └───────────────────┘
```

---

## Factories

All models have factories for testing:

```php
// File: /database/factories/Accounting/InvoiceFactory.php

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-' . $this->faker->unique()->numerify('####'),
            'contact_id' => Contact::factory(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => Invoice::STATUS_DRAFT,
            'tax_rate' => 11.00,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
        ];
    }

    public function posted(): static
    {
        return $this->state(['status' => Invoice::STATUS_POSTED]);
    }

    public function withItems(int $count = 3): static
    {
        return $this->has(InvoiceItem::factory()->count($count));
    }
}
```

---

## Related Documentation

- [ADR-0005: Single Namespace](../08-adr/0005-single-accounting-namespace.md)
- [ADR-0008: Integer Currency](../08-adr/0008-integer-currency-storage.md)
- [Service Layer](./service-layer.md)
- [API Design](./api-design.md)
