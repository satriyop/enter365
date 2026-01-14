---
adr: "0035"
title: "API Resource Conventions"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [api, resources]
related_adrs: [0034]
related_modules: [api]
impact: medium
---

# ADR-0035: API Resource Conventions

## AI Agent Quick Reference

**Use this ADR when:**
- Creating API Resources
- Transforming model data
- Building API responses
- Understanding response structure

**Key takeaway:** Use Laravel API Resources for all responses with consistent structure.

---

## Decision

Use Laravel API Resources for all API responses with standardized structure and relationships.

---

## Context

API responses need:
1. Consistent structure
2. Controlled data exposure
3. Relationship handling
4. Pagination support

---

## Implementation

### Resource Structure

```php
// app/Http/Resources/InvoiceResource.php
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'status' => $this->status,

            // Monetary values
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'amount_due' => $this->amount_due,

            // Formatted for display
            'subtotal_formatted' => Currency::format($this->subtotal),
            'total_formatted' => Currency::format($this->total),

            // Relationships (conditional)
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### Response Wrappers

```php
// Single resource
return new InvoiceResource($invoice);
// Output: { "data": { ... } }

// Collection
return InvoiceResource::collection($invoices);
// Output: { "data": [ ... ] }

// Paginated
return InvoiceResource::collection($invoices->paginate(15));
// Output: { "data": [ ... ], "links": { ... }, "meta": { ... } }
```

### Naming Conventions

| Model | Resource | Collection |
|-------|----------|------------|
| Invoice | InvoiceResource | InvoiceResource::collection() |
| InvoiceItem | InvoiceItemResource | InvoiceItemResource::collection() |
| Contact | ContactResource | ContactResource::collection() |

### Conditional Relationships

```php
// Only include when explicitly loaded
'contact' => new ContactResource($this->whenLoaded('contact')),
'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

// Include with condition
'latest_payment' => $this->when(
    $this->relationLoaded('payments'),
    fn () => new PaymentResource($this->payments->first())
),
```

### Include Additional Data

```php
// With meta information
public function with(Request $request): array
{
    return [
        'meta' => [
            'api_version' => 'v1',
        ],
    ];
}
```

### Computed Properties

```php
// Add computed/virtual attributes
'is_overdue' => $this->isOverdue(),
'days_overdue' => $this->daysOverdue(),
'can_edit' => $this->canEdit(),
'can_delete' => $this->canDelete(),
```

### Resource File Location

```
app/Http/Resources/
├── ContactResource.php
├── InvoiceResource.php
├── InvoiceItemResource.php
├── QuotationResource.php
├── ProductResource.php
├── PaymentResource.php
└── ...
```

---

## References

- [ADR-0034: API Versioning](./0034-api-versioning.md)
- [API Design](../01-architecture/api-design.md)

