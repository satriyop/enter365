---
adr: "0037"
title: "API Filtering"
status: accepted
date: 2024-11-15
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
- Building query pipelines
- Understanding filter conventions

**Key takeaway:** Use query string filters with consistent naming (filter[field]=value).

---

## Decision

Use query string filters with `filter[field]` syntax and dedicated filter classes.

---

## Context

API endpoints need:
1. Field-level filtering
2. Date range filtering
3. Status filtering
4. Search functionality

---

## Implementation

### Query String Format

```
GET /api/v1/invoices?filter[status]=draft&filter[contact_id]=5&filter[date_from]=2024-01-01
```

### Filter Class Pattern

```php
// app/Http/Filters/InvoiceFilter.php
class InvoiceFilter
{
    protected Builder $query;
    protected array $filters;

    public function __construct(Builder $query, array $filters)
    {
        $this->query = $query;
        $this->filters = $filters;
    }

    public function apply(): Builder
    {
        foreach ($this->filters as $filter => $value) {
            if (method_exists($this, $filter) && !empty($value)) {
                $this->$filter($value);
            }
        }

        return $this->query;
    }

    protected function status(string $value): void
    {
        $this->query->where('status', $value);
    }

    protected function contact_id(int $value): void
    {
        $this->query->where('contact_id', $value);
    }

    protected function date_from(string $value): void
    {
        $this->query->whereDate('date', '>=', $value);
    }

    protected function date_to(string $value): void
    {
        $this->query->whereDate('date', '<=', $value);
    }

    protected function search(string $value): void
    {
        $this->query->where(function ($q) use ($value) {
            $q->where('number', 'like', "%{$value}%")
              ->orWhereHas('contact', fn ($q) =>
                  $q->where('name', 'like', "%{$value}%")
              );
        });
    }

    protected function min_amount(int $value): void
    {
        $this->query->where('total', '>=', $value);
    }

    protected function max_amount(int $value): void
    {
        $this->query->where('total', '<=', $value);
    }
}
```

### Controller Usage

```php
public function index(Request $request)
{
    $query = Invoice::with(['contact', 'items']);

    $filters = $request->input('filter', []);
    $query = (new InvoiceFilter($query, $filters))->apply();

    // Sorting
    $sortField = $request->input('sort', '-date');
    $sortDir = str_starts_with($sortField, '-') ? 'desc' : 'asc';
    $sortField = ltrim($sortField, '-');
    $query->orderBy($sortField, $sortDir);

    return InvoiceResource::collection($query->paginate());
}
```

### Common Filters

| Filter | Example | Description |
|--------|---------|-------------|
| status | `filter[status]=draft` | Exact match |
| search | `filter[search]=INV-2024` | Partial match |
| date_from | `filter[date_from]=2024-01-01` | Range start |
| date_to | `filter[date_to]=2024-01-31` | Range end |
| contact_id | `filter[contact_id]=5` | Foreign key |

### Sorting Convention

```
GET /api/v1/invoices?sort=date      # ASC
GET /api/v1/invoices?sort=-date     # DESC (prefix with -)
GET /api/v1/invoices?sort=-date,-number  # Multiple fields
```

### Form Request Validation

```php
// app/Http/Requests/InvoiceIndexRequest.php
public function rules(): array
{
    return [
        'filter.status' => ['nullable', Rule::in(['draft', 'sent', 'paid'])],
        'filter.contact_id' => ['nullable', 'exists:contacts,id'],
        'filter.date_from' => ['nullable', 'date'],
        'filter.date_to' => ['nullable', 'date', 'after_or_equal:filter.date_from'],
        'filter.search' => ['nullable', 'string', 'max:100'],
        'sort' => ['nullable', 'string', Rule::in(['date', '-date', 'number', '-number'])],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
    ];
}
```

### Scope-Based Alternative

```php
// In Model
public function scopeFilter(Builder $query, array $filters): Builder
{
    return (new InvoiceFilter($query, $filters))->apply();
}

// In Controller
Invoice::filter($request->input('filter', []))->paginate();
```

---

## References

- [ADR-0036: API Pagination](./0036-api-pagination.md)
- [API Design](../01-architecture/api-design.md)

