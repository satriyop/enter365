---
adr: "0005"
title: "Single Accounting Namespace for All Models"
status: accepted
date: 2024-11-01
deciders: [Architecture Team]
tags: [architecture, structure, organization]
related_adrs: [0001, 0003, 0007]
related_modules: [all]
impact: high
---

# ADR-0005: Single Accounting Namespace for All Models

## AI Agent Quick Reference

**Use this ADR when:**
- Creating new models (put in `App\Models\Accounting\`)
- Creating new services (put in `App\Services\Accounting\`)
- Understanding code organization
- Finding where code lives

**Key takeaway:** All accounting/ERP models and services live under a single `Accounting` namespace. No domain-based subdirectories.

---

## Context

Enter365 has 70+ Eloquent models and 39 services covering:
- Sales (Quotation, Invoice, Payment)
- Purchasing (PO, GRN, Bill)
- Inventory (Product, Warehouse, Stock)
- Manufacturing (BOM, WorkOrder, MRP)
- Projects (Project, Cost, Revenue)
- Solar (SolarProposal, PLN Tariff)

### Forces

- **Interconnected Domains** - Invoice links to Payment, Product, Contact, JournalEntry
- **Shared Concepts** - Product used in sales, purchasing, manufacturing
- **Developer Experience** - Easy to find models
- **Simplicity** - Avoid over-engineering

---

## Decision Drivers

1. **Interconnected Data** - Models reference each other across "domains"
2. **Simplicity** - Flat structure easier to navigate
3. **Laravel Convention** - Follow standard Laravel patterns
4. **Pragmatism** - Avoid DDD complexity for SME application
5. **Single Context** - All models serve same bounded context

---

## Considered Options

### Option 1: Single Accounting Namespace (Chosen)

**Description:** All models in `App\Models\Accounting\`, services in `App\Services\Accounting\`

**Pros:**
- Simple flat structure
- Easy to find any model
- Reflects interconnected nature
- No import path confusion
- Laravel conventional

**Cons:**
- Large directories (70+ files)
- No visual domain separation
- May seem less "sophisticated"

### Option 2: Domain-Based Namespaces

**Description:** `App\Models\Sales\Invoice`, `App\Models\Purchasing\Bill`, etc.

**Pros:**
- Visual domain separation
- Smaller directories
- Feels more "enterprise"

**Cons:**
- Artificial boundaries (Product in which domain?)
- Cross-domain imports everywhere
- Harder to navigate relationships
- Over-engineering for SME app

### Option 3: Module-Based Packages

**Description:** Separate Laravel packages per domain

**Pros:**
- Full isolation
- Independent deployment possible

**Cons:**
- Massive over-engineering
- Complex dependency management
- Overkill for monolith

---

## Decision

**Chosen option:** "Single Accounting Namespace"

All models live in `App\Models\Accounting\`, all services in `App\Services\Accounting\`. This reflects the reality that accounting data is deeply interconnected.

---

## Rationale

### Why Single Namespace:

1. **Data Reality**
   - Invoice references: Contact, Product, JournalEntry, Payment, DownPayment
   - Product references: BOM, Warehouse, Category, Stock, Component
   - Everything is interconnected

2. **Developer Experience**
   - One place to look: `App\Models\Accounting\`
   - Simple imports: `use App\Models\Accounting\Invoice;`
   - IDE autocomplete works naturally

3. **Pragmatic Architecture**
   - Enter365 is a monolith for SMEs
   - Not microservices, not enterprise DDD
   - Simple structure = faster development

4. **Feature Flags Instead**
   - Module toggling via feature flags (ADR-0007)
   - Not namespace separation
   - `middleware('feature:mrp')` for MRP routes

---

## Consequences

### Positive

- Simple, flat structure
- Easy navigation
- No import confusion
- Faster development
- Laravel conventional

### Negative

- Large directories (70 models, 39 services)
- No visual domain boundaries
- Need discipline to avoid god objects

### Neutral

- IDE file search is primary navigation
- Feature flags provide module separation
- Directory listing requires scrolling

---

## Implementation Notes

**Directory Structure:**

```
app/
├── Models/
│   ├── Accounting/             # 70 models
│   │   ├── Account.php
│   │   ├── AuditLog.php
│   │   ├── Bill.php
│   │   ├── BillItem.php
│   │   ├── Bom.php
│   │   ├── BomItem.php
│   │   ├── BomTemplate.php
│   │   ├── BomVariantGroup.php
│   │   ├── Contact.php
│   │   ├── DeliveryOrder.php
│   │   ├── DownPayment.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── JournalEntry.php
│   │   ├── JournalEntryLine.php
│   │   ├── MaterialRequisition.php
│   │   ├── MrpRun.php
│   │   ├── Payment.php
│   │   ├── Product.php
│   │   ├── ProductStock.php
│   │   ├── Project.php
│   │   ├── PurchaseOrder.php
│   │   ├── Quotation.php
│   │   ├── SolarProposal.php
│   │   ├── Warehouse.php
│   │   ├── WorkOrder.php
│   │   └── ... (70 total)
│   └── User.php                # User is outside (Laravel default)
│
├── Services/
│   └── Accounting/             # 39 services
│       ├── BomService.php
│       ├── BomVariantGroupService.php
│       ├── FinancialReportService.php
│       ├── InventoryService.php
│       ├── JournalService.php
│       ├── MrpService.php
│       ├── QuotationService.php
│       └── ... (39 total)
│
└── Http/
    └── Controllers/
        └── Api/
            └── V1/             # 53 controllers
                ├── BomController.php
                ├── InvoiceController.php
                ├── QuotationController.php
                └── ...
```

**Model Organization (Alphabetical):**

Models are organized alphabetically, not by domain:

```php
// Finding models is simple
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Quotation;
```

**Service Organization:**

```php
// Services follow same pattern
use App\Services\Accounting\QuotationService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\MrpService;
```

**Example Model:**

```php
// File: /app/Models/Accounting/Invoice.php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    // Relationships span "domains" freely
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

    public function journalEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'source');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }
}
```

**Key Patterns:**

| Aspect | Pattern |
|--------|---------|
| Models | `App\Models\Accounting\ModelName` |
| Services | `App\Services\Accounting\ModelNameService` |
| Controllers | `App\Http\Controllers\Api\V1\ModelNameController` |
| Resources | `App\Http\Resources\Api\V1\ModelNameResource` |
| Requests | `App\Http\Requests\Api\V1\ModelName\ActionRequest` |

---

## Validation

**Verification Steps:**

1. Count models: `ls app/Models/Accounting/*.php | wc -l` (expect 70)
2. Count services: `ls app/Services/Accounting/*.php | wc -l` (expect 39)
3. Verify no subdirectories in `Models/Accounting/`
4. Check imports use single namespace

**Tests:**

```php
// Tests also follow flat structure
// tests/Feature/Api/V1/QuotationApiTest.php
// tests/Feature/Api/V1/InvoiceApiTest.php
```

---

## References

- ADR-0003: Service Layer Pattern
- ADR-0007: Feature Flag System
- `/app/Models/Accounting/` - All 70 models
- `/app/Services/Accounting/` - All 39 services

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Backend Team
