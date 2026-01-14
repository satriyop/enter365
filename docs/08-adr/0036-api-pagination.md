---
adr: "0036"
title: "API Pagination"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [api, pagination]
related_adrs: [0034, 0035]
related_modules: [api]
impact: low
---

# ADR-0036: API Pagination

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing list endpoints
- Working with large datasets
- Building infinite scroll
- Understanding pagination params

**Key takeaway:** Use cursor-based pagination for large datasets, page-based for admin lists.

---

## Decision

Support both page-based and cursor-based pagination, defaulting to 15 items per page.

---

## Context

Different use cases need different pagination:
1. Admin lists: Page-based (jump to page)
2. Mobile apps: Cursor-based (infinite scroll)
3. Reports: All records (no pagination)

---

## Implementation

### Default Configuration

```php
// config/accounting.php
'pagination' => [
    'default_per_page' => 15,
    'max_per_page' => 100,
],
```

### Page-Based Pagination

```php
// Controller
public function index(Request $request)
{
    $perPage = min(
        $request->input('per_page', 15),
        config('accounting.pagination.max_per_page')
    );

    return InvoiceResource::collection(
        Invoice::with(['contact', 'items'])
            ->paginate($perPage)
    );
}
```

### Response Format (Page-Based)

```json
{
    "data": [ ... ],
    "links": {
        "first": "http://api/v1/invoices?page=1",
        "last": "http://api/v1/invoices?page=10",
        "prev": null,
        "next": "http://api/v1/invoices?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

### Cursor-Based Pagination

```php
// For infinite scroll / large datasets
public function index(Request $request)
{
    return InvoiceResource::collection(
        Invoice::orderBy('id')
            ->cursorPaginate(15)
    );
}
```

### Response Format (Cursor-Based)

```json
{
    "data": [ ... ],
    "meta": {
        "path": "http://api/v1/invoices",
        "per_page": 15,
        "next_cursor": "eyJpZCI6MTV9",
        "prev_cursor": null
    }
}
```

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | int | 1 | Page number |
| per_page | int | 15 | Items per page |
| cursor | string | null | Cursor for cursor pagination |

### Performance Considerations

```php
// Page-based: SELECT COUNT(*) needed
Invoice::paginate(15);

// Cursor-based: No COUNT needed, faster for large tables
Invoice::cursorPaginate(15);

// Simple pagination: No last page, faster
Invoice::simplePaginate(15);
```

### When to Use Each

| Type | Use When |
|------|----------|
| paginate() | Admin tables, need total count |
| simplePaginate() | Lists where total not needed |
| cursorPaginate() | Infinite scroll, large datasets |

---

## References

- [ADR-0035: API Resource Conventions](./0035-api-resource-conventions.md)
- [API Design](../01-architecture/api-design.md)

