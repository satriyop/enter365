# Scaffold Filter Skill

Generate a QueryFilter class with appropriate trait composition.

## Trigger

Use when user says:
- `/scaffold-filter`
- "create filter for X"
- "add filtering to Y model"

## Required Information

Prompt the user for:
1. **Model name** - e.g., `Invoice`, `WorkOrder`
2. **Search fields** - What text fields should be searchable?
3. **Has status?** - Does the model have `status` or `is_active` fields?
4. **Has date field?** - What's the primary date field? (e.g., `invoice_date`, `created_at`)
5. **Foreign keys** - Which relationships need filtering? (contact_id, project_id, etc.)
6. **Custom filters** - Any special filters? (e.g., `low_stock`, `overdue`)

## File Generated

```
app/Filters/{Model}Filter.php
```

---

## Trait Reference

| Trait | Methods Provided | When to Use |
|-------|------------------|-------------|
| `HasSearchFilter` | `search()`, `searchExact()`, `keyword()` | Model has text fields to search |
| `HasStatusFilter` | `status()`, `statuses()`, `excludeStatus()`, `isActive()` | Model has status or is_active |
| `HasDateRangeFilter` | `startDate()`, `endDate()`, `dateRange()`, `month()`, `year()` | Model has date field |
| `HasRelationFilter` | `contactId()`, `projectId()`, `warehouseId()`, etc. | Model has foreign keys |

---

## Template: Basic Filter (Search + Status)

```php
<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for {Model} queries.
 *
 * Supported filters:
 * - search: Search by {fields}
 * - is_active: Active status
 */
class {Model}Filter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'code', 'description'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'name';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'name',
            'code',
            'created_at',
            'updated_at',
        ];
    }
}
```

---

## Template: Document Filter (All Traits)

For document models (Invoice, Quotation, PurchaseOrder, etc.):

```php
<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasRelationFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for {Model} queries.
 *
 * Supported filters:
 * - search: Search by {model}_number, description
 * - status: Filter by status
 * - contact_id: Filter by contact
 * - start_date / end_date: Date range filtering
 */
class {Model}Filter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasRelationFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['{model}_number', 'description', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return '{model}_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return '{model}_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            '{model}_number',
            '{model}_date',
            'due_date',
            'total',
            'status',
            'created_at',
        ];
    }
}
```

---

## Request Dispatch Rules (do not regress)

`QueryFilter` maps kebab-case query params onto **public** methods on the concrete filter and its traits. Do not change `shouldApplyFilter()` back to `method_exists()` — that reopens infrastructure methods (`apply`, getters) as `?apply=1` 500s. Sort `direction` is allowlisted to `asc`/`desc`. `HasSearchFilter::keyword()` must stay driver-aware (no MySQL-only `REGEXP`). See [Gotcha #30](../enter365/SKILL.md#30-queryfilter-method_exists-is-not-an-allowlist).

---

## Adding Custom Filter Methods

### Filter by Enum/Type

```php
/**
 * Filter by type.
 */
public function type(string $value): void
{
    $this->builder->where('type', $value);
}
```

### Filter by Boolean

```php
/**
 * Filter by track inventory flag.
 */
public function trackInventory(bool|string $value): void
{
    $this->builder->where('track_inventory', filter_var($value, FILTER_VALIDATE_BOOLEAN));
}
```

### Filter by Scope

```php
/**
 * Filter only low stock products.
 */
public function lowStock(bool|string $value): void
{
    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
        $this->builder->lowStock(); // Calls model scope
    }
}
```

### Filter by Relationship Existence

```php
/**
 * Filter only items with payments.
 */
public function hasPay(bool|string $value): void
{
    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
        $this->builder->has('payments');
    } else {
        $this->builder->doesntHave('payments');
    }
}
```

### Filter by Amount Range

```php
/**
 * Filter by minimum amount.
 */
public function minAmount(int|string $value): void
{
    $this->builder->where('total', '>=', (int) $value);
}

/**
 * Filter by maximum amount.
 */
public function maxAmount(int|string $value): void
{
    $this->builder->where('total', '<=', (int) $value);
}
```

---

## HasSearchFilter Configuration

The `HasSearchFilter` trait provides these methods:

| Method | URL Parameter | Behavior |
|--------|---------------|----------|
| `search()` | `?search=term` | LIKE search on all searchable fields |
| `searchExact()` | `?search_exact=term` | Exact match on searchable fields |
| `keyword()` | `?keyword=term` | Word boundary search |

**Configure searchable fields:**

```php
protected function getSearchableFields(): array
{
    return [
        'name',           // Direct column
        'sku',            // Direct column
        'contact.name',   // Relationship column (uses whereHas)
    ];
}
```

---

## HasStatusFilter Configuration

The `HasStatusFilter` trait provides:

| Method | URL Parameter | Behavior |
|--------|---------------|----------|
| `status()` | `?status=draft` | Single status filter |
| `statuses()` | `?statuses=draft,approved` | Multiple statuses (comma-separated) |
| `excludeStatus()` | `?exclude_status=cancelled` | Exclude status |
| `isActive()` | `?is_active=true` | Boolean active filter |

**Configure status column (if not 'status'):**

```php
protected function getStatusColumn(): string
{
    return 'state'; // Override if column name differs
}
```

---

## HasDateRangeFilter Configuration

The `HasDateRangeFilter` trait provides:

| Method | URL Parameter | Example |
|--------|---------------|---------|
| `startDate()` | `?start_date=2024-01-01` | From date |
| `endDate()` | `?end_date=2024-12-31` | To date |
| `dateRange()` | `?date_range=2024-01-01,2024-12-31` | Range |
| `month()` | `?month=2024-01` | Filter by month |
| `year()` | `?year=2024` | Filter by year |

**Configure date field:**

```php
protected function getDateField(): string
{
    return 'invoice_date'; // Default is 'created_at'
}
```

---

## HasRelationFilter Methods

Pre-built methods for common foreign keys:

| Method | URL Parameter | Column |
|--------|---------------|--------|
| `contactId()` | `?contact_id=1` | `contact_id` |
| `projectId()` | `?project_id=1` | `project_id` |
| `warehouseId()` | `?warehouse_id=1` | `warehouse_id` |
| `productId()` | `?product_id=1` | `product_id` |
| `categoryId()` | `?category_id=1` | `category_id` |
| `bomId()` | `?bom_id=1` | `bom_id` |
| `createdBy()` | `?created_by=1` | `created_by` |

**For other foreign keys, add custom method:**

```php
public function vendorId(int|string $value): void
{
    $this->builder->where('vendor_id', $value);
}
```

---

## Integration with Model

Ensure the model uses the `Filterable` trait:

```php
// In Model
use App\Traits\Filterable;

class Invoice extends Model
{
    use Filterable;
    // ...
}
```

This enables the `filter()` scope:

```php
// In Controller
Invoice::query()->filter($filter)->paginate();
```

---

## Execution Checklist

1. [ ] Determine which traits are needed
2. [ ] Create filter class with appropriate traits
3. [ ] Configure `getSearchableFields()` for search
4. [ ] Configure `getDateField()` if using date filter
5. [ ] Add custom filter methods for special cases
6. [ ] Configure sort fields (`getAllowedSortFields()`)
7. [ ] Ensure model has `Filterable` trait
8. [ ] Run `vendor/bin/pint` to format
9. [ ] Test filters via API calls

---

## Example Usage

**User:** `/scaffold-filter`

**Claude:** I'll create a filter class. Please provide:
1. Model name?
2. What text fields should be searchable?
3. Does it have status/is_active?
4. What's the date field? (or none)
5. What foreign keys need filtering?
6. Any custom filters needed?

**User:**
- Model: WorkOrder
- Search: work_order_number, description
- Status: yes (uses DocumentStatus)
- Date field: work_order_date
- Foreign keys: bom_id, project_id
- Custom: filter by assigned_to user

**Claude:** Creating WorkOrderFilter with:
- HasSearchFilter (work_order_number, description)
- HasStatusFilter
- HasDateRangeFilter (work_order_date)
- Custom methods: bomId(), projectId(), assignedTo()
