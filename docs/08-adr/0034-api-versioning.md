---
adr: "0034"
title: "API Versioning"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [api, versioning]
related_adrs: [0004]
related_modules: [api]
impact: medium
---

# ADR-0034: API Versioning

## AI Agent Quick Reference

**Use this ADR when:**
- Creating new API endpoints
- Understanding API structure
- Planning breaking changes
- Working with mobile app integration

**Key takeaway:** API uses URL-based versioning (/api/v1/) with v1 as current stable version.

---

## Decision

Use URL-based API versioning with `/api/v1/` prefix for all API endpoints.

---

## Context

API versioning needed for:
1. Mobile app compatibility
2. Third-party integrations
3. Breaking changes management
4. Backward compatibility

---

## Implementation

### URL Structure

```
/api/v1/invoices           # v1 API
/api/v2/invoices           # Future v2 (when needed)
```

### Route Organization

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('quotations', QuotationController::class);
        // ... other resources
    });
});

// Future: v2 when breaking changes needed
// Route::prefix('v2')->group(function () { ... });
```

### Controller Namespacing

```
app/Http/Controllers/Api/
├── V1/
│   ├── InvoiceController.php
│   ├── QuotationController.php
│   └── ...
└── V2/  (future)
    └── ...
```

### Version Header (Optional)

```php
// For version negotiation when needed
Request::header('Accept-Version'); // "v1" or "v2"
```

### API Response Format

```json
{
    "data": { ... },
    "meta": {
        "api_version": "v1",
        "timestamp": "2024-01-25T10:30:00Z"
    }
}
```

### Breaking Change Policy

| Change Type | Action |
|-------------|--------|
| New field | Add to v1 (non-breaking) |
| Optional param | Add to v1 (non-breaking) |
| Remove field | New version (v2) |
| Change field type | New version (v2) |
| Change validation | Deprecate first, then v2 |

### Deprecation Notice

```php
// Response header for deprecated endpoints
return response()
    ->json($data)
    ->header('X-Deprecated', 'This endpoint will be removed in v2. Use /api/v2/new-endpoint instead.');
```

---

## References

- [ADR-0004: Sanctum Authentication](./0004-sanctum-authentication.md)
- [API Design](../01-architecture/api-design.md)

