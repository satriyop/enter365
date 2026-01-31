# API Resource Patterns

API resource transformation patterns.

---

## Location

```
app/Http/Resources/Api/V1/
├── InvoiceResource.php
├── InvoiceItemResource.php
├── QuotationResource.php
└── ...
```

---

## Basic Template

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\YourModule\YourModel
 */
class YourModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,

            // Foreign key + relationship
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            // Nested collection
            'items' => YourModelItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),

            // Status with metadata
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Computed fields
            'can_edit' => $this->canEdit(),
            'can_submit' => $this->canSubmit(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

---

## Transformation Patterns

### Basic Fields

```php
'id' => $this->id,
'name' => $this->name,
'description' => $this->description,
```

### Type Casting

```php
'tax_rate' => (float) $this->tax_rate,
'quantity' => (float) $this->quantity,
'exchange_rate' => (float) $this->exchange_rate,
```

### Date Formatting

```php
// ISO 8601 (preferred for APIs)
'created_at' => $this->created_at?->toIso8601String(),

// Date only
'invoice_date' => $this->invoice_date->toDateString(),

// Custom format
'shipped_at' => $this->shipped_at?->format('Y-m-d H:i:s'),
```

---

## Relationship Patterns

### whenLoaded() - Nested Resource

```php
'contact' => new ContactResource($this->whenLoaded('contact')),
'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
```

### whenLoaded() - Collection

```php
'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
'payments' => PaymentResource::collection($this->whenLoaded('payments')),
```

### whenLoaded() - Inline Object

```php
'product' => $this->whenLoaded('product', fn () => [
    'id' => $this->product->id,
    'name' => $this->product->name,
    'sku' => $this->product->sku,
]),
```

### whenLoaded() - Mapped Collection

```php
'roles' => $this->whenLoaded('roles', function () {
    return $this->roles->map(fn ($role) => [
        'id' => $role->id,
        'name' => $role->name,
    ]);
}),
```

### whenCounted()

```php
'items_count' => $this->whenCounted('items'),
'payments_count' => $this->whenCounted('payments'),
```

---

## Conditional Fields

### Simple Condition

```php
'can_edit' => $this->canEdit(),
'can_submit' => $this->canSubmit(),
'can_approve' => $this->canApprove(),
```

### Request Parameter Condition

```php
'workflow' => $this->when(
    $request->boolean('include_workflow'),
    fn () => $this->getWorkflowMetadata()
),

'status_history' => $this->when(
    $request->boolean('include_history'),
    fn () => $this->getStatusTimeline()
),
```

### mergeWhen()

```php
$this->mergeWhen($this->isOverdue(), [
    'days_overdue' => $this->getDaysOverdue(),
]),
```

---

## Metadata Patterns

### Status Object

```php
'status' => [
    'value' => $this->status->value,
    'label' => $this->status->label(),
    'color' => $this->status->color(),
    'is_terminal' => $this->status->isTerminal(),
],
```

### Actions/Permissions Group

```php
'actions' => [
    'can_edit' => $stateMachine->canEdit(),
    'can_submit' => $stateMachine->canSubmit(),
    'can_approve' => $stateMachine->canApprove(),
    'can_cancel' => $stateMachine->canCancel(),
],
```

### Formatted Money

```php
'subtotal' => $this->subtotal,
'total_amount' => $this->total_amount,
'formatted' => [
    'subtotal' => $this->formatMoney($this->subtotal),
    'total_amount' => $this->formatMoney($this->total_amount),
],
```

---

## Item Resource Template

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\YourModule\YourModelItem
 */
class YourModelItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,

            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),

            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'discount_percent' => (float) $this->discount_percent,
            'tax_rate' => (float) $this->tax_rate,
            'line_total' => $this->line_total,

            'sort_order' => $this->sort_order,
        ];
    }
}
```

---

## Collection Usage

```php
// In Controller

// Single resource
return new InvoiceResource($invoice);

// Collection (auto-wrapped)
return InvoiceResource::collection($invoices);

// Paginated
return InvoiceResource::collection($invoices->paginate());
```

---

## Rich Resource with State Machine

```php
public function toArray(Request $request): array
{
    $stateMachine = InvoiceStateMachine::fromInvoice($this->resource);
    $outstandingAmount = $this->getOutstandingAmount();

    return [
        'id' => $this->id,
        'invoice_number' => $this->invoice_number,

        // Financial
        'subtotal' => $this->subtotal,
        'tax_amount' => $this->tax_amount,
        'total_amount' => $this->total_amount,
        'paid_amount' => $this->paid_amount,
        'outstanding_amount' => $outstandingAmount,

        // Status
        'status' => $this->status,
        'status_label' => $this->status->label(),

        // Actions from state machine
        'actions' => [
            'can_edit' => $stateMachine->canEdit(),
            'can_send' => $stateMachine->canSend(),
            'can_void' => $stateMachine->canVoid(),
        ],

        // Optional workflow metadata
        'workflow' => $this->when(
            $request->boolean('include_workflow'),
            fn () => $stateMachine->getWorkflowMetadata()
        ),

        // Relationships
        'contact' => new ContactResource($this->whenLoaded('contact')),
        'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        'payments' => PaymentResource::collection($this->whenLoaded('payments')),

        // Timestamps
        'invoice_date' => $this->invoice_date->toDateString(),
        'due_date' => $this->due_date->toDateString(),
        'created_at' => $this->created_at?->toIso8601String(),
    ];
}
```

---

## Conventions

| Aspect | Convention |
|--------|------------|
| Dates | `toIso8601String()` for timestamps |
| Floats | Explicit `(float)` cast |
| Relationships | Always use `whenLoaded()` |
| Counts | Use `whenCounted()` |
| Nullable | Use `$this->field?->` safe operator |
| DocBlock | Include `@mixin` for IDE support |

---

## Artisan Command

```bash
php artisan make:resource Api/V1/YourModelResource --no-interaction
```

---

## Contract Validation

**IMPORTANT:** After creating or modifying API Resources:

1. **Run integration check:**
   ```bash
   ./scripts/check-api-integration.sh
   ```

2. **Pre-commit hook** will automatically validate before commit

3. **CI/CD** validates on every PR

**What it checks:**
- Resource fields match OpenAPI schema (`api.json`)
- Field names are consistent
- Types are correct
- No missing or extra fields

**Field Naming Standards:**
- ✅ Use `_amount` suffix: `total_amount`, `discount_amount`, `tax_amount`
- ✅ Be consistent across all Resources
- ✅ Match database column names

**Common Issues:**
- Field in Resource but not in Schema → Add to Scramble annotations
- Field in Schema but not in Resource → Add to `toArray()` method
- Type mismatch → Fix type casting or PHPDoc

**Workflow:**
1. Modify Resource
2. Run `./scripts/check-api-integration.sh`
3. Fix any mismatches
4. Update tests if field names changed
5. Commit (hook validates automatically)

See `docs/04-api/integration-check/` for detailed documentation.
