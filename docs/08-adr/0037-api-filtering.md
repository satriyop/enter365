---
adr: "0037"
title: "API Filtering"
status: accepted
date: 2024-11-15
updated: 2026-01-15
deciders: [Product Team]
tags: [api, filtering, search]
related_adrs: [0034, 0036]
related_modules: [api]
impact: medium
---

# ADR-0037: API Filtering

## AI Agent Quick Reference

**Use this ADR when:**
- Adding filters to list endpoints
- Implementing search functionality
- Creating new filter classes
- Understanding filter conventions

**Key takeaway:** Use QueryFilter base class with composable traits. Controllers inject filters via dependency injection.

---

## Decision

Use a `QueryFilter` base class with composable traits (`HasDateRangeFilter`, `HasStatusFilter`, `HasSearchFilter`) and the `Filterable` model trait for clean separation of concerns.

---

## Context

API endpoints need:
1. Field-level filtering
2. Date range filtering
3. Status filtering
4. Search functionality

The previous implementation used inline filtering in controllers, leading to:
- 500+ lines of duplicated filtering logic across 20+ controllers
- Inconsistent filter behavior
- Hard to test filtering in isolation

---

## Implementation

### Architecture Overview

```
app/Filters/
├── QueryFilter.php              # Base class
├── Traits/
│   ├── HasDateRangeFilter.php   # Reusable date filtering
│   ├── HasStatusFilter.php      # Reusable status filtering
│   └── HasSearchFilter.php      # Reusable search filtering
├── InvoiceFilter.php
├── BillFilter.php
├── PaymentFilter.php
├── ProductFilter.php
├── PurchaseOrderFilter.php
├── DeliveryOrderFilter.php
├── SalesReturnFilter.php
├── PurchaseReturnFilter.php
├── QuotationFilter.php
├── WorkOrderFilter.php
└── ProjectFilter.php
```

### Query String Format

```
GET /api/v1/invoices?status=draft&contact_id=5&date_from=2024-01-01&search=INV
```

### Base QueryFilter Class

```php
// app/Filters/QueryFilter.php
namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    protected Builder $builder;
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->all() as $filter => $value) {
            $method = Str::camel($filter);
            if (method_exists($this, $method) && $value !== null && $value !== '') {
                $this->$method($value);
            }
        }

        return $this->builder;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
```

### Filterable Trait (Model)

```php
// app/Traits/Filterable.php
namespace App\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
```

### Reusable Filter Traits

```php
// app/Filters/Traits/HasDateRangeFilter.php
namespace App\Filters\Traits;

trait HasDateRangeFilter
{
    abstract protected function getDateField(): string;

    public function dateFrom(string $value): void
    {
        $this->builder->whereDate($this->getDateField(), '>=', $value);
    }

    public function dateTo(string $value): void
    {
        $this->builder->whereDate($this->getDateField(), '<=', $value);
    }
}
```

```php
// app/Filters/Traits/HasStatusFilter.php
namespace App\Filters\Traits;

trait HasStatusFilter
{
    public function status(string $value): void
    {
        $this->builder->where('status', $value);
    }
}
```

```php
// app/Filters/Traits/HasSearchFilter.php
namespace App\Filters\Traits;

trait HasSearchFilter
{
    abstract protected function getSearchableFields(): array;

    public function search(string $value): void
    {
        $fields = $this->getSearchableFields();

        $this->builder->where(function ($query) use ($value, $fields) {
            foreach ($fields as $field) {
                if (str_contains($field, '.')) {
                    // Relationship search: 'contact.name'
                    [$relation, $column] = explode('.', $field, 2);
                    $query->orWhereHas($relation, fn ($q) =>
                        $q->where($column, 'ilike', "%{$value}%")
                    );
                } else {
                    $query->orWhere($field, 'ilike', "%{$value}%");
                }
            }
        });
    }
}
```

### Concrete Filter Class Example

```php
// app/Filters/InvoiceFilter.php
namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

class InvoiceFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    protected function getDateField(): string
    {
        return 'invoice_date';
    }

    protected function getSearchableFields(): array
    {
        return ['invoice_number', 'subject', 'reference', 'contact.name'];
    }

    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }
}
```

### Model Setup

```php
// app/Models/Sales/Invoice.php
namespace App\Models\Sales;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use Filterable;
    // ...
}
```

### Controller Usage (Dependency Injection)

```php
// app/Http/Controllers/Api/V1/InvoiceController.php
namespace App\Http\Controllers\Api\V1;

use App\Filters\InvoiceFilter;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Sales\Invoice;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function index(InvoiceFilter $filter): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with(['contact', 'items'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return InvoiceResource::collection($invoices);
    }
}
```

### Common Filters

| Filter | Example | Description |
|--------|---------|-------------|
| status | `?status=draft` | Exact match |
| search | `?search=INV-2024` | Multi-field partial match |
| date_from | `?date_from=2024-01-01` | Range start |
| date_to | `?date_to=2024-01-31` | Range end |
| contact_id | `?contact_id=5` | Foreign key |

### Available Filter Classes

| Filter Class | Used By | Special Filters |
|--------------|---------|-----------------|
| `InvoiceFilter` | InvoiceController | status, contactId, search |
| `BillFilter` | BillController | status, contactId, search |
| `PaymentFilter` | PaymentController | type, isVoided, contactId |
| `ProductFilter` | ProductController | type, category, active, sellable, purchasable, lowStock |
| `PurchaseOrderFilter` | PurchaseOrderController | status, outstandingOnly, activeOnly |
| `DeliveryOrderFilter` | DeliveryOrderController | status, warehouseId, invoiceId |
| `SalesReturnFilter` | SalesReturnController | status, reason, invoiceId |
| `PurchaseReturnFilter` | PurchaseReturnController | status, reason, billId |
| `QuotationFilter` | QuotationController | status, contactId, projectId |
| `WorkOrderFilter` | WorkOrderController | status, type, projectId, bomId |
| `ProjectFilter` | ProjectController | status, contactId |

### Adding a New Filter

1. Create filter class extending `QueryFilter`:

```php
// app/Filters/NewModelFilter.php
namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

class NewModelFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    protected function getDateField(): string
    {
        return 'created_at';
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'description'];
    }

    // Add custom filters as needed
    public function customField(string $value): void
    {
        $this->builder->where('custom_field', $value);
    }
}
```

2. Add `Filterable` trait to model:

```php
use App\Traits\Filterable;

class NewModel extends Model
{
    use Filterable;
}
```

3. Inject filter in controller:

```php
public function index(NewModelFilter $filter): AnonymousResourceCollection
{
    return NewModelResource::collection(
        NewModel::query()->filter($filter)->paginate()
    );
}
```

---

## Benefits

1. **DRY**: Eliminated 500+ lines of inline filtering across controllers
2. **Testable**: Filter classes can be unit tested in isolation
3. **Composable**: Traits allow mixing common filter behaviors
4. **Type-safe**: Dependency injection provides IDE support
5. **Consistent**: All endpoints follow the same filtering pattern

---

## References

- [ADR-0036: API Pagination](./0036-api-pagination.md)
- [Controller Pattern](../07-code-patterns/controller-pattern.md)
- [API Design](../01-architecture/api-design.md)

