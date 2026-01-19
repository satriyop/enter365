---
pattern: filter
title: "Query Filter Pattern"
location: app/Filters/
tags: [api, query, filtering]
updated: 2026-01-19
---

# Query Filter Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Building API endpoints with filtering
- Creating reusable query logic for models
- Implementing search, date range, or status filters
- Adding sorting to list endpoints

**Key files:**
- Base class: `/app/Filters/QueryFilter.php`
- Trait: `/app/Models/Traits/Filterable.php`
- Filters: `/app/Filters/{Entity}Filter.php`

---

## Existing Filters

| Filter | Model | Supported Filters |
|--------|-------|-------------------|
| InvoiceFilter | Invoice | status, contact_id, date range, search |
| QuotationFilter | Quotation | status, contact_id, date range, search |
| BillFilter | Bill | status, contact_id, date range, search |
| PurchaseOrderFilter | PurchaseOrder | status, contact_id, date range, search |
| DeliveryOrderFilter | DeliveryOrder | status, invoice_id, date range, search |
| WorkOrderFilter | WorkOrder | status, project_id, date range, search |
| ProductFilter | Product | category_id, is_active, search |
| PaymentFilter | Payment | type, contact_id, date range, search |
| ProjectFilter | Project | status, contact_id, date range, search |
| SalesReturnFilter | SalesReturn | status, invoice_id, date range, search |
| PurchaseReturnFilter | PurchaseReturn | status, bill_id, date range, search |

---

## Architecture

```
app/
├── Filters/
│   ├── QueryFilter.php           # Abstract base class
│   ├── Traits/
│   │   ├── HasDateRangeFilter.php
│   │   ├── HasSearchFilter.php
│   │   └── HasStatusFilter.php
│   ├── InvoiceFilter.php
│   ├── QuotationFilter.php
│   └── ...
│
└── Models/
    └── Traits/
        └── Filterable.php        # Model trait for applying filters
```

---

## Base Filter Class

```php
<?php
// File: app/Filters/QueryFilter.php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    protected Builder $builder;
    protected Request $request;

    /**
     * Parameters excluded from automatic filtering.
     */
    protected array $excludedParameters = [
        'page',
        'per_page',
        'sort',
        'direction',
        'order',
        'limit',
        'offset',
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply all filters to the query builder.
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->getFilterableParameters() as $name => $value) {
            $method = Str::camel($name);

            if ($this->shouldApplyFilter($method, $value)) {
                $this->{$method}($value);
            }
        }

        $this->applySorting();

        return $this->builder;
    }

    /**
     * Get parameters that should be used for filtering.
     */
    protected function getFilterableParameters(): array
    {
        return collect($this->request->all())
            ->except($this->excludedParameters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    /**
     * Check if a filter method should be applied.
     */
    protected function shouldApplyFilter(string $method, mixed $value): bool
    {
        return method_exists($this, $method)
            && $value !== null
            && $value !== '';
    }

    /**
     * Apply sorting to the query.
     */
    protected function applySorting(): void
    {
        $sortField = $this->request->input('sort', $this->getDefaultSortField());
        $sortDirection = $this->request->input('direction', $this->getDefaultSortDirection());

        if ($sortField && $this->isValidSortField($sortField)) {
            $this->builder->orderBy($sortField, $sortDirection);
        }
    }

    protected function getDefaultSortField(): ?string
    {
        return 'created_at';
    }

    protected function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    protected function getAllowedSortFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    protected function isValidSortField(string $field): bool
    {
        return in_array($field, $this->getAllowedSortFields(), true);
    }
}
```

---

## Filter Traits

### HasStatusFilter

```php
<?php
// File: app/Filters/Traits/HasStatusFilter.php

declare(strict_types=1);

namespace App\Filters\Traits;

trait HasStatusFilter
{
    /**
     * Filter by status.
     */
    public function status(string $value): void
    {
        $this->builder->where('status', $value);
    }
}
```

### HasDateRangeFilter

```php
<?php
// File: app/Filters/Traits/HasDateRangeFilter.php

declare(strict_types=1);

namespace App\Filters\Traits;

trait HasDateRangeFilter
{
    /**
     * Filter from start date.
     */
    public function startDate(string $value): void
    {
        $this->builder->where($this->getDateField(), '>=', $value);
    }

    /**
     * Filter to end date.
     */
    public function endDate(string $value): void
    {
        $this->builder->where($this->getDateField(), '<=', $value);
    }

    /**
     * Get the date field to filter on.
     */
    protected function getDateField(): string
    {
        return 'created_at';
    }
}
```

### HasSearchFilter

```php
<?php
// File: app/Filters/Traits/HasSearchFilter.php

declare(strict_types=1);

namespace App\Filters\Traits;

trait HasSearchFilter
{
    /**
     * Search across multiple fields.
     */
    public function search(string $value): void
    {
        $fields = $this->getSearchableFields();

        $this->builder->where(function ($query) use ($value, $fields) {
            foreach ($fields as $field) {
                if (str_contains($field, '.')) {
                    // Relationship field: contact.name
                    [$relation, $column] = explode('.', $field, 2);
                    $query->orWhereHas($relation, function ($q) use ($column, $value) {
                        $q->where($column, 'ILIKE', "%{$value}%");
                    });
                } else {
                    $query->orWhere($field, 'ILIKE', "%{$value}%");
                }
            }
        });
    }

    /**
     * Get fields that are searchable.
     */
    protected function getSearchableFields(): array
    {
        return [];
    }
}
```

---

## Creating a New Filter

### Step 1: Create Filter Class

```php
<?php
// File: app/Filters/{Entity}Filter.php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for {Entity} queries.
 *
 * Supported filters:
 * - status: {Entity} status (draft, approved, etc.)
 * - contact_id: Filter by contact
 * - start_date: Date from
 * - end_date: Date to
 * - search: Search by number, description, or contact name
 */
class {Entity}Filter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * Fields to search across.
     */
    protected function getSearchableFields(): array
    {
        return ['{entity}_number', 'description', 'contact.name'];
    }

    /**
     * Date field for range filtering.
     */
    protected function getDateField(): string
    {
        return '{entity}_date';
    }

    /**
     * Default sort field.
     */
    protected function getDefaultSortField(): ?string
    {
        return '{entity}_date';
    }

    /**
     * Allowed sort fields (whitelist).
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            '{entity}_number',
            '{entity}_date',
            'status',
            'total_amount',
            'created_at',
            'updated_at',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Custom Filters
    // ─────────────────────────────────────────────────────────────

    /**
     * Filter by contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by project.
     */
    public function projectId(int|string $value): void
    {
        $this->builder->where('project_id', $value);
    }

    /**
     * Filter by active/inactive.
     */
    public function isActive(string|bool $value): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->builder->where('is_active', $isActive);
    }
}
```

### Step 2: Add Filterable Trait to Model

```php
<?php
// File: app/Models/{Domain}/{Entity}.php

namespace App\Models\{Domain};

use App\Models\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

class {Entity} extends Model
{
    use Filterable;

    // ...
}
```

The Filterable trait:

```php
<?php
// File: app/Models/Traits/Filterable.php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply filters to query.
     */
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
```

### Step 3: Use in Controller

```php
<?php
// File: app/Http/Controllers/Api/V1/{Domain}/{Entity}Controller.php

use App\Filters\{Entity}Filter;
use App\Http\Resources\Api\V1\{Domain}\{Entity}Resource;
use App\Models\{Domain}\{Entity};

class {Entity}Controller extends Controller
{
    public function index({Entity}Filter $filter): AnonymousResourceCollection
    {
        $entities = {Entity}::query()
            ->with(['contact', 'items'])  // Eager load
            ->filter($filter)              // Apply filters
            ->paginate($filter->getRequest()->input('per_page', 25));

        return {Entity}Resource::collection($entities);
    }
}
```

---

## API Usage Examples

### Basic Filtering

```bash
# Filter by status
GET /api/v1/invoices?status=sent

# Filter by contact
GET /api/v1/invoices?contact_id=123

# Combine filters
GET /api/v1/invoices?status=sent&contact_id=123
```

### Date Range

```bash
# Filter by date range
GET /api/v1/invoices?start_date=2024-01-01&end_date=2024-12-31
```

### Search

```bash
# Search across configured fields
GET /api/v1/invoices?search=INV-2024
```

### Sorting

```bash
# Sort by field (ascending)
GET /api/v1/invoices?sort=total_amount&direction=asc

# Sort by field (descending)
GET /api/v1/invoices?sort=invoice_date&direction=desc
```

### Pagination

```bash
# Paginate with custom page size
GET /api/v1/invoices?page=2&per_page=50
```

### Combined Example

```bash
GET /api/v1/invoices?status=sent&start_date=2024-01-01&search=ABC&sort=total_amount&direction=desc&per_page=25
```

---

## Custom Filter Methods

### Relationship Filters

```php
/**
 * Filter by items containing a specific product.
 */
public function productId(int $value): void
{
    $this->builder->whereHas('items', function ($query) use ($value) {
        $query->where('product_id', $value);
    });
}
```

### Aggregate Filters

```php
/**
 * Filter by minimum total amount.
 */
public function minAmount(int $value): void
{
    $this->builder->where('total_amount', '>=', $value);
}

/**
 * Filter by maximum total amount.
 */
public function maxAmount(int $value): void
{
    $this->builder->where('total_amount', '<=', $value);
}
```

### Boolean Filters

```php
/**
 * Filter overdue invoices.
 */
public function overdue(string|bool $value): void
{
    $isOverdue = filter_var($value, FILTER_VALIDATE_BOOLEAN);

    if ($isOverdue) {
        $this->builder
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled']);
    }
}
```

### Array Filters

```php
/**
 * Filter by multiple statuses.
 */
public function statuses(string|array $value): void
{
    $statuses = is_array($value) ? $value : explode(',', $value);
    $this->builder->whereIn('status', $statuses);
}
```

---

## Testing Filters

```php
<?php

use App\Filters\InvoiceFilter;
use App\Models\Sales\Invoice;
use Illuminate\Http\Request;

describe('InvoiceFilter', function () {

    it('filters by status', function () {
        Invoice::factory()->count(3)->create(['status' => 'draft']);
        Invoice::factory()->count(2)->create(['status' => 'sent']);

        $request = Request::create('/', 'GET', ['status' => 'draft']);
        $filter = new InvoiceFilter($request);

        $result = Invoice::query()->filter($filter)->get();

        expect($result)->toHaveCount(3);
        expect($result->pluck('status')->unique()->toArray())->toBe(['draft']);
    });

    it('filters by date range', function () {
        Invoice::factory()->create(['invoice_date' => '2024-01-15']);
        Invoice::factory()->create(['invoice_date' => '2024-02-15']);
        Invoice::factory()->create(['invoice_date' => '2024-03-15']);

        $request = Request::create('/', 'GET', [
            'start_date' => '2024-02-01',
            'end_date' => '2024-02-28',
        ]);
        $filter = new InvoiceFilter($request);

        $result = Invoice::query()->filter($filter)->get();

        expect($result)->toHaveCount(1);
    });

    it('searches across multiple fields', function () {
        $contact = Contact::factory()->create(['name' => 'ABC Company']);
        Invoice::factory()->create(['contact_id' => $contact->id]);
        Invoice::factory()->create(['invoice_number' => 'INV-XYZ-001']);

        $request = Request::create('/', 'GET', ['search' => 'ABC']);
        $filter = new InvoiceFilter($request);

        $result = Invoice::query()->filter($filter)->get();

        expect($result)->toHaveCount(1);
    });

    it('sorts by allowed fields', function () {
        Invoice::factory()->create(['total_amount' => 100_00]);
        Invoice::factory()->create(['total_amount' => 300_00]);
        Invoice::factory()->create(['total_amount' => 200_00]);

        $request = Request::create('/', 'GET', [
            'sort' => 'total_amount',
            'direction' => 'asc',
        ]);
        $filter = new InvoiceFilter($request);

        $result = Invoice::query()->filter($filter)->get();

        expect($result->pluck('total_amount')->toArray())->toBe([100_00, 200_00, 300_00]);
    });

    it('ignores invalid sort fields', function () {
        $request = Request::create('/', 'GET', ['sort' => 'malicious_field']);
        $filter = new InvoiceFilter($request);

        // Should not throw, should use default
        $result = Invoice::query()->filter($filter)->toSql();

        expect($result)->not->toContain('malicious_field');
    });
});
```

---

## Related Documents

- [Controller Pattern](./controller-pattern.md)
- [API Design](../01-architecture/api-design.md)
- [Model Pattern](./model-pattern.md)
