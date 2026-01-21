# Form Request Patterns

Validation patterns for API requests.

---

## Naming Convention

| Operation | Pattern | Example |
|-----------|---------|---------|
| Create | `Store{Entity}Request` | `StoreInvoiceRequest` |
| Update | `Update{Entity}Request` | `UpdateInvoiceRequest` |
| Action | `{Action}{Entity}Request` | `ApplyDownPaymentRequest` |

---

## Location

```
app/Http/Requests/Api/V1/
├── StoreInvoiceRequest.php
├── UpdateInvoiceRequest.php
├── StoreQuotationRequest.php
└── ...
```

---

## Basic Template

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYourModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Required fields
            'name' => ['required', 'string', 'max:255'],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],

            // Nullable fields
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Dates
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],

            // Numeric
            'amount' => ['nullable', 'integer', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Enum
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],

            // Items array
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'contact_id.required' => 'Kontak harus dipilih.',
            'contact_id.exists' => 'Kontak tidak ditemukan.',
            'items.required' => 'Item wajib diisi.',
            'items.min' => 'Minimal satu item harus diisi.',
            'items.*.description.required' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required' => 'Jumlah item harus diisi.',
        ];
    }
}
```

---

## Rule Patterns

### Required vs Optional

```php
// Required (Store)
'name' => ['required', 'string', 'max:255'],

// Optional (Update - use sometimes)
'name' => ['sometimes', 'required', 'string', 'max:255'],
```

### Foreign Key

```php
'contact_id' => ['required', 'integer', 'exists:contacts,id'],
'product_id' => ['nullable', 'integer', 'exists:products,id'],
```

### Dates

```php
'invoice_date' => ['required', 'date'],
'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
```

### Numeric

```php
// Integer (amounts in smallest unit)
'amount' => ['required', 'integer', 'min:0'],

// Decimal (rates, percentages)
'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

// Quantity
'quantity' => ['required', 'numeric', 'min:0.0001'],
```

### Enum

```php
use Illuminate\Validation\Rule;

'status' => ['required', Rule::in(['active', 'inactive'])],
'priority' => ['nullable', 'string', Rule::in(QuotationPriority::ALL)],
'type' => ['required', Rule::in(array_keys(BomItem::getTypes()))],
```

### Unique

```php
// Store (new record)
'email' => ['required', 'email', Rule::unique('users', 'email')],

// Update (ignore current record)
'email' => [
    'sometimes',
    'email',
    Rule::unique('users', 'email')->ignore($this->route('user')->id),
],
```

### File Upload

```php
'file' => ['required', 'file', 'max:10240'],  // 10MB
```

---

## Array Validation

```php
// Basic array
'items' => ['required', 'array', 'min:1'],

// Nested fields
'items.*.product_id' => ['nullable', 'exists:products,id'],
'items.*.description' => ['required', 'string', 'max:500'],
'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
'items.*.unit' => ['nullable', 'string', 'max:20'],
'items.*.unit_price' => ['required', 'integer', 'min:0'],
'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
```

### Array Messages

```php
'items.required' => 'Item harus diisi.',
'items.min' => 'Minimal satu item harus diisi.',
'items.*.description.required' => 'Deskripsi item harus diisi.',
'items.*.quantity.min' => 'Jumlah item harus lebih dari 0.',
```

---

## Conditional Validation

### Role-Based Rules

```php
public function rules(): array
{
    $rules = [
        'name' => ['sometimes', 'string', 'max:255'],
    ];

    if ($this->user()?->isAdmin()) {
        $rules['is_active'] = ['sometimes', 'boolean'];
        $rules['roles'] = ['sometimes', 'array'];
    }

    return $rules;
}
```

### Cross-Field Validation

```php
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $lines = $this->input('lines', []);
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += $line['debit'] ?? 0;
            $totalCredit += $line['credit'] ?? 0;
        }

        if ($totalDebit !== $totalCredit) {
            $validator->errors()->add(
                'lines',
                'Total debit harus sama dengan total kredit.'
            );
        }
    });
}
```

### Required Without

```php
'debit' => ['required_without:credit', 'integer', 'min:0'],
'credit' => ['required_without:debit', 'integer', 'min:0'],
```

---

## Authorization Patterns

```php
// Always allow (authentication handled elsewhere)
public function authorize(): bool
{
    return true;
}

// Admin only
public function authorize(): bool
{
    return $this->user()?->isAdmin();
}

// Resource owner
public function authorize(): bool
{
    $targetUser = $this->route('user');
    return $this->user()?->isAdmin()
        || $this->user()?->id === $targetUser->id;
}
```

---

## Indonesian Messages Reference

| Rule | Indonesian |
|------|------------|
| `required` | `X wajib diisi.` / `X harus diisi.` |
| `exists` | `X tidak ditemukan.` |
| `unique` | `X sudah digunakan.` |
| `email` | `Format email tidak valid.` |
| `min` (array) | `Minimal X item harus diisi.` |
| `min` (numeric) | `X minimal adalah Y.` |
| `max` (string) | `X maksimal Y karakter.` |
| `date` | `Format tanggal tidak valid.` |
| `after_or_equal` | `Tanggal akhir harus sama atau setelah tanggal mulai.` |

---

## Update Request Differences

```php
class UpdateYourModelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Use 'sometimes' for partial updates
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_id' => ['sometimes', 'required', 'integer', 'exists:contacts,id'],

            // Items might be optional in update
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],  // Existing item ID
            'items.*.description' => ['required', 'string', 'max:500'],
        ];
    }
}
```

---

## Artisan Command

```bash
php artisan make:request Api/V1/StoreYourModelRequest --no-interaction
```
