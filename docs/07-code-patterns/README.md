---
section: code-patterns
title: "Code Patterns"
description: "Coding conventions and patterns used in Enter365"
---

# Code Patterns

## AI Agent Quick Reference

**Use this section when:**
- Creating new services or controllers
- Understanding code conventions
- Writing tests
- Following project patterns

---

## Available Patterns

| Pattern | File | Purpose |
|---------|------|---------|
| [Service Pattern](./service-pattern.md) | Services | Business logic encapsulation |
| [Controller Pattern](./controller-pattern.md) | Controllers | Request handling |
| [Model Pattern](./model-pattern.md) | Models | Eloquent conventions |
| [Testing Pattern](./testing-pattern.md) | Tests | Pest testing conventions |

---

## General Principles

### 1. Services Handle Business Logic

```php
// ✓ Good: Controller delegates to service
public function store(StoreInvoiceRequest $request)
{
    $invoice = $this->invoiceService->create($request->validated());
    return new InvoiceResource($invoice);
}

// ✗ Bad: Business logic in controller
public function store(StoreInvoiceRequest $request)
{
    $invoice = Invoice::create($request->validated());
    $invoice->calculateTotals();
    $invoice->createJournalEntry();
    // ... more logic
}
```

### 2. Models Are Thin

```php
// ✓ Good: Model has relationships and simple accessors
class Invoice extends Model
{
    public function contact(): BelongsTo { }
    public function items(): HasMany { }
    public function getAmountDueAttribute(): int { }
}

// ✗ Bad: Model has business logic
class Invoice extends Model
{
    public function sendToCustomer() { }  // Should be in service
    public function runMrp() { }          // Should be in service
}
```

### 3. Form Requests Validate

```php
// ✓ Good: All validation in Form Request
class StoreInvoiceRequest extends FormRequest
{
    public function rules(): array { }
    public function authorize(): bool { }
}
```

### 4. Resources Transform Output

```php
// ✓ Good: API Resource controls output
class InvoiceResource extends JsonResource
{
    public function toArray($request): array { }
}
```

---

## File Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Service | `{Entity}Service.php` | `InvoiceService.php` |
| Controller | `{Entity}Controller.php` | `InvoiceController.php` |
| Model | `{Entity}.php` | `Invoice.php` |
| Form Request | `{Action}{Entity}Request.php` | `StoreInvoiceRequest.php` |
| Resource | `{Entity}Resource.php` | `InvoiceResource.php` |
| Test | `{Entity}Test.php` | `InvoiceTest.php` |
| Policy | `{Entity}Policy.php` | `InvoicePolicy.php` |

---

## Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Accounting/           # Grouped by module
│   ├── Requests/
│   │   └── Invoice/              # Grouped by entity
│   └── Resources/
│
├── Models/
│   └── Accounting/               # All in Accounting namespace
│
├── Services/
│   └── Accounting/               # Business logic services
│
└── Policies/

tests/
├── Feature/
│   └── {Module}/
│       └── Epic-{N}/             # Grouped by User Story Epic
└── Unit/
```

---

## Cross-References

- [Service Layer Architecture](../01-architecture/service-layer.md)
- [API Design](../01-architecture/api-design.md)
- [ADR-0003: Service Layer Pattern](../08-adr/0003-service-layer-pattern.md)

