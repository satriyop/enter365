---
pattern: controller
title: "Controller Pattern"
location: app/Http/Controllers/
tags: [architecture, controllers]
---

# Controller Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Creating new API endpoints
- Handling HTTP requests
- Delegating to services
- Returning API responses

**Key rule:** Controllers are thin - only handle HTTP concerns, delegate business logic to services.

---

## Controller Structure

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\ApproveInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    /**
     * List invoices with filtering and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with(['contact', 'items'])
            ->filter($request->input('filter', []))
            ->orderBy($request->input('sort', 'date'), 'desc')
            ->paginate($request->input('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    /**
     * Show a single invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource(
            $invoice->load(['contact', 'items.product', 'payments'])
        );
    }

    /**
     * Create a new invoice.
     */
    public function store(StoreInvoiceRequest $request): InvoiceResource
    {
        $invoice = $this->invoiceService->create($request->validated());

        return new InvoiceResource($invoice);
    }

    /**
     * Update an invoice.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return new InvoiceResource($invoice);
    }

    /**
     * Delete an invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete($invoice);

        return response()->json(null, 204);
    }

    // ─────────────────────────────────────────────────────────────
    // Action Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * Approve an invoice.
     */
    public function approve(ApproveInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoiceService->approve($invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Send invoice to customer.
     */
    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        $this->invoiceService->sendToCustomer($invoice, $request->input('email'));

        return response()->json([
            'message' => 'Invoice sent successfully.',
        ]);
    }

    /**
     * Duplicate an invoice.
     */
    public function duplicate(Invoice $invoice): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $newInvoice = $this->invoiceService->duplicate($invoice);

        return new InvoiceResource($newInvoice);
    }
}
```

---

## Key Principles

### 1. Inject Service via Constructor

```php
public function __construct(
    private InvoiceService $invoiceService
) {}
```

### 2. Use Form Requests for Validation

```php
public function store(StoreInvoiceRequest $request): InvoiceResource
{
    // $request is already validated and authorized
    $invoice = $this->invoiceService->create($request->validated());
    return new InvoiceResource($invoice);
}
```

### 3. Use Resources for Output

```php
// Single resource
return new InvoiceResource($invoice);

// Collection with pagination
return InvoiceResource::collection($invoices);
```

### 4. Authorize with Policies

```php
public function show(Invoice $invoice): InvoiceResource
{
    $this->authorize('view', $invoice);
    // ...
}
```

---

## Route Registration

```php
// routes/api.php
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // RESTful resource routes
    Route::apiResource('invoices', InvoiceController::class);

    // Action routes
    Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate']);
});
```

---

## Controller Method Responsibilities

| Method | HTTP | Responsibility |
|--------|------|----------------|
| index | GET | List with filter/paginate |
| show | GET | Single resource with relations |
| store | POST | Create via service |
| update | PUT/PATCH | Update via service |
| destroy | DELETE | Delete via service |
| {action} | POST | Business action via service |

---

## What NOT to Do

```php
// ✗ Bad: Business logic in controller
public function store(Request $request)
{
    $validated = $request->validate([...]); // Should be Form Request

    $invoice = Invoice::create($validated);
    $invoice->items()->createMany($validated['items']);

    // Calculating in controller - bad!
    $subtotal = $invoice->items->sum('subtotal');
    $tax = $subtotal * 0.11;
    $invoice->update(['subtotal' => $subtotal, 'tax' => $tax]);

    // Journal entry in controller - bad!
    JournalEntry::create([...]);

    return response()->json($invoice); // Should use Resource
}
```

---

## Livewire Page Controllers

For Livewire/Volt pages, controllers are optional. Use route closures or Folio:

```php
// routes/web.php
Route::get('/invoices', fn () => view('pages.invoices.index'))
    ->middleware('auth')
    ->name('invoices.index');
```

---

## Related Documents

- [Service Pattern](./service-pattern.md)
- [ADR-0039: Form Request Validation](../08-adr/0039-form-request-validation.md)
- [ADR-0035: API Resource Conventions](../08-adr/0035-api-resource-conventions.md)
- [API Design](../01-architecture/api-design.md)

