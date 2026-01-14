---
adr: "0039"
title: "Form Request Validation"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [api, validation]
related_adrs: [0038]
related_modules: [api]
impact: medium
---

# ADR-0039: Form Request Validation

## AI Agent Quick Reference

**Use this ADR when:**
- Creating Form Request classes
- Implementing validation rules
- Adding authorization checks
- Understanding validation patterns

**Key takeaway:** Always use Form Request classes, never inline validation in controllers.

---

## Decision

Use dedicated Form Request classes for all API validation with authorization in the same class.

---

## Context

Validation needs:
1. Reusable validation logic
2. Authorization in same place
3. Custom error messages
4. Complex conditional rules

---

## Implementation

### Form Request Structure

```php
// app/Http/Requests/StoreInvoiceRequest.php
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'exists:contacts,id'],
            'date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:date'],
            'reference' => ['nullable', 'string', 'max:50'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one line item is required.',
            'items.*.product_id.required' => 'Each item must have a product.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'contact_id' => 'customer',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
        ];
    }
}
```

### Update Request Pattern

```php
// app/Http/Requests/UpdateInvoiceRequest.php
class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->can('update', $invoice)
            && $invoice->canEdit();  // Business rule
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:date'],
            'items' => ['sometimes', 'array', 'min:1'],
            // ... similar to store but with 'sometimes'
        ];
    }
}
```

### Controller Usage

```php
// Controller is clean
public function store(StoreInvoiceRequest $request)
{
    // $request already validated and authorized
    $invoice = $this->invoiceService->create($request->validated());

    return new InvoiceResource($invoice);
}
```

### Complex Validation Rules

```php
public function rules(): array
{
    return [
        'amount' => [
            'required',
            'integer',
            'min:0',
            // Custom rule for max payment
            function ($attribute, $value, $fail) {
                $invoice = Invoice::find($this->invoice_id);
                if ($value > $invoice->amount_due) {
                    $fail('Payment cannot exceed amount due.');
                }
            },
        ],
    ];
}
```

### Conditional Validation

```php
public function rules(): array
{
    $rules = [
        'type' => ['required', Rule::in(['sale', 'refund'])],
    ];

    // Conditional rules based on type
    if ($this->type === 'refund') {
        $rules['original_invoice_id'] = ['required', 'exists:invoices,id'];
        $rules['reason'] = ['required', 'string', 'max:255'];
    }

    return $rules;
}

// Or using Rule::when
'original_invoice_id' => [
    Rule::when($this->type === 'refund', ['required', 'exists:invoices,id']),
],
```

### Prepared Validation Data

```php
protected function prepareForValidation(): void
{
    // Clean/transform input before validation
    $this->merge([
        'date' => $this->date ? Carbon::parse($this->date) : null,
        'amount' => (int) preg_replace('/[^0-9]/', '', $this->amount ?? 0),
    ]);
}
```

### Naming Convention

| Action | Class Name |
|--------|------------|
| Create | StoreInvoiceRequest |
| Update | UpdateInvoiceRequest |
| Delete | DeleteInvoiceRequest |
| List | IndexInvoiceRequest |
| Custom | ApproveInvoiceRequest |

### File Location

```
app/Http/Requests/
├── Invoice/
│   ├── StoreInvoiceRequest.php
│   ├── UpdateInvoiceRequest.php
│   └── ApproveInvoiceRequest.php
├── Quotation/
│   └── ...
└── ...
```

---

## References

- [ADR-0038: API Error Handling](./0038-api-error-handling.md)
- [API Design](../01-architecture/api-design.md)

