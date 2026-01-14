---
adr: "0040"
title: "API Action Endpoints"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [api, actions]
related_adrs: [0034, 0035]
related_modules: [api]
impact: low
---

# ADR-0040: API Action Endpoints

## AI Agent Quick Reference

**Use this ADR when:**
- Adding non-CRUD actions
- Implementing status transitions
- Creating bulk operations
- Understanding action conventions

**Key takeaway:** Use POST for actions on resources: POST /invoices/{id}/approve, POST /invoices/{id}/send.

---

## Decision

Use POST method for resource actions with verb-based endpoints appended to resource URL.

---

## Context

Beyond CRUD, APIs need:
1. Status transitions (approve, reject)
2. Business actions (send, print)
3. Bulk operations
4. Relationship actions

---

## Implementation

### Action Endpoint Pattern

```
POST /api/v1/invoices/{id}/approve
POST /api/v1/invoices/{id}/send
POST /api/v1/invoices/{id}/cancel
POST /api/v1/quotations/{id}/convert
```

### Route Definition

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('invoices', InvoiceController::class);

    // Action routes
    Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate']);
});
```

### Controller Actions

```php
class InvoiceController extends Controller
{
    public function approve(ApproveInvoiceRequest $request, Invoice $invoice)
    {
        $invoice = $this->invoiceService->approve($invoice);

        return new InvoiceResource($invoice);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->invoiceService->send($invoice, $request->email);

        return response()->json([
            'message' => 'Invoice sent successfully.',
        ]);
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice)
    {
        $invoice = $this->invoiceService->cancel($invoice, $request->reason);

        return new InvoiceResource($invoice);
    }
}
```

### Bulk Actions

```
POST /api/v1/invoices/bulk-approve
POST /api/v1/invoices/bulk-delete
```

```php
// Route
Route::post('invoices/bulk-approve', [InvoiceController::class, 'bulkApprove']);

// Controller
public function bulkApprove(BulkApproveRequest $request)
{
    $ids = $request->input('ids');

    $approved = Invoice::whereIn('id', $ids)
        ->where('status', 'draft')
        ->get()
        ->each(fn ($invoice) => $this->invoiceService->approve($invoice));

    return response()->json([
        'message' => "{$approved->count()} invoices approved.",
        'approved_ids' => $approved->pluck('id'),
    ]);
}

// Request body
{
    "ids": [1, 2, 3, 5, 8]
}
```

### Conversion Actions

```
POST /api/v1/quotations/{id}/convert
```

```php
public function convert(ConvertQuotationRequest $request, Quotation $quotation)
{
    $invoice = $this->quotationService->convertToInvoice($quotation);

    return response()->json([
        'message' => 'Quotation converted to invoice.',
        'invoice' => new InvoiceResource($invoice),
    ]);
}
```

### Action Response Patterns

| Action | Response |
|--------|----------|
| Status change | Return updated resource |
| Send email | Return success message |
| Convert | Return new resource |
| Bulk | Return count + affected IDs |

### Common Actions by Resource

| Resource | Actions |
|----------|---------|
| Invoice | approve, send, cancel, duplicate |
| Quotation | approve, send, convert, duplicate |
| PurchaseOrder | approve, receive, cancel |
| WorkOrder | start, complete, cancel |
| Payment | approve, cancel, void |

### Action Request Classes

```php
// app/Http/Requests/Invoice/ApproveInvoiceRequest.php
class ApproveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->can('approve', $invoice)
            && $invoice->status === 'draft';
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

---

## References

- [ADR-0035: API Resource Conventions](./0035-api-resource-conventions.md)
- [API Design](../01-architecture/api-design.md)

