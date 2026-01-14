---
section: architecture
title: "API Design"
order: 5
---

# API Design

> **RESTful API conventions for Enter365**
>
> 418 routes following consistent patterns, versioned under `/api/v1/`.

---

## AI Agent Quick Reference

**Use this document when:**
- Creating new API endpoints
- Understanding route conventions
- Debugging API responses
- Working with authentication

**Key takeaway:** All routes follow RESTful conventions under `/api/v1/`, protected by `auth:sanctum` middleware.

---

## API Overview

| Metric | Count |
|--------|-------|
| Total Routes | 418 |
| API Version | v1 |
| Controllers | 53 |
| Auth Method | Sanctum (Bearer token) |
| Response Format | JSON |

---

## Route Structure

### Base URL

```
/api/v1/
```

### Authentication

```
Authorization: Bearer {token}
```

All routes except public ones require Sanctum authentication.

### Route Groups

```php
// File: /routes/api.php

Route::prefix('v1')->group(function () {
    // Public routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::prefix('public')->group(function () {
        Route::get('solar-proposals/{token}', [PublicSolarProposalController::class, 'show']);
        Route::post('solar-calculator/calculate', [PublicSolarCalculatorController::class, 'calculate']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);

        // Core resources
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('contacts', ContactController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('journal-entries', JournalEntryController::class);

        // Sales
        Route::apiResource('quotations', QuotationController::class);
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('payments', PaymentController::class);

        // Purchasing
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::apiResource('bills', BillController::class);

        // Feature-gated routes
        Route::middleware('feature:manufacturing')->group(function () {
            Route::apiResource('boms', BomController::class);
            Route::apiResource('work-orders', WorkOrderController::class);
        });

        Route::middleware('feature:mrp')->group(function () {
            Route::get('mrp', [MrpController::class, 'index']);
            Route::post('mrp/run', [MrpController::class, 'run']);
        });
    });
});
```

---

## RESTful Conventions

### Standard CRUD Routes

```
GET    /api/v1/quotations           → index   (list all)
POST   /api/v1/quotations           → store   (create)
GET    /api/v1/quotations/{id}      → show    (get one)
PUT    /api/v1/quotations/{id}      → update  (update)
DELETE /api/v1/quotations/{id}      → destroy (delete)
```

### Custom Actions

```
POST   /api/v1/quotations/{id}/submit        → submit for approval
POST   /api/v1/quotations/{id}/approve       → approve
POST   /api/v1/quotations/{id}/reject        → reject
POST   /api/v1/quotations/{id}/convert       → convert to invoice
POST   /api/v1/quotations/{id}/duplicate     → create copy
POST   /api/v1/quotations/{id}/revise        → create revision
```

### Nested Resources

```
GET    /api/v1/quotations/{id}/items         → list items
POST   /api/v1/quotations/{id}/items         → add item
DELETE /api/v1/quotations/{id}/items/{itemId} → remove item
```

---

## Request/Response Format

### Request Headers

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

### Successful Response (200, 201)

```json
{
    "data": {
        "id": 1,
        "quotation_number": "QUO-202401-0001",
        "contact": {
            "id": 5,
            "name": "PT Maju Jaya"
        },
        "items": [...],
        "subtotal": 10000000,
        "subtotal_formatted": "Rp 100.000,00",
        "total": 11100000,
        "total_formatted": "Rp 111.000,00"
    }
}
```

### Collection Response (Paginated)

```json
{
    "data": [
        {...},
        {...}
    ],
    "links": {
        "first": "/api/v1/quotations?page=1",
        "last": "/api/v1/quotations?page=10",
        "prev": null,
        "next": "/api/v1/quotations?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 25,
        "to": 25,
        "total": 250
    }
}
```

### Validation Error (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "contact_id": ["Pelanggan wajib diisi."],
        "items": ["Minimal 1 item diperlukan."]
    }
}
```

### Not Found (404)

```json
{
    "message": "Quotation tidak ditemukan."
}
```

### Unauthorized (401)

```json
{
    "message": "Unauthenticated."
}
```

### Forbidden (403)

```json
{
    "message": "Anda tidak memiliki akses."
}
```

---

## Controller Pattern

```php
// File: /app/Http/Controllers/Api/V1/QuotationController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Quotation\StoreQuotationRequest;
use App\Http\Requests\Api\V1\Quotation\UpdateQuotationRequest;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Accounting\Quotation;
use App\Services\Accounting\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $quotations = Quotation::with(['contact', 'items'])
            ->latest()
            ->paginate(25);

        return QuotationResource::collection($quotations);
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $quotation = $this->quotationService->create($request->validated());

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        return new QuotationResource(
            $quotation->load(['contact', 'items.product', 'createdBy'])
        );
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        $quotation = $this->quotationService->update($quotation, $request->validated());

        return new QuotationResource($quotation);
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return response()->json(['message' => 'Quotation dihapus.']);
    }

    public function submit(Quotation $quotation): QuotationResource
    {
        $quotation = $this->quotationService->submit($quotation);

        return new QuotationResource($quotation);
    }

    public function approve(Quotation $quotation): QuotationResource
    {
        $quotation = $this->quotationService->approve($quotation);

        return new QuotationResource($quotation);
    }
}
```

---

## Form Request Validation

```php
// File: /app/Http/Requests/Api/V1/Quotation/StoreQuotationRequest.php

namespace App\Http\Requests\Api\V1\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or policy check
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'exists:contacts,id'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:quotation_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required' => 'Pelanggan wajib dipilih.',
            'items.required' => 'Minimal 1 item diperlukan.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
        ];
    }
}
```

---

## API Resource Transformation

```php
// File: /app/Http/Resources/Api/V1/QuotationResource.php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'quotation_number' => $this->quotation_number,
            'revision' => $this->revision,
            'quotation_date' => $this->quotation_date->toDateString(),
            'valid_until' => $this->valid_until->toDateString(),
            'status' => $this->status,

            // Related resources
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),

            // Amounts (raw integers for calculations)
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,

            // Formatted amounts (for display)
            'subtotal_formatted' => $this->formatted_subtotal,
            'total_formatted' => $this->formatted_total,

            // Tax
            'tax_rate' => $this->tax_rate,

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Workflow
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),

            // Audit
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
        ];
    }
}
```

---

## Query Parameters

### Pagination

```
GET /api/v1/quotations?page=2&per_page=50
```

Default: 25 items per page.

### Filtering

```
GET /api/v1/quotations?status=draft&contact_id=5
GET /api/v1/quotations?date_from=2024-01-01&date_to=2024-12-31
```

### Sorting

```
GET /api/v1/quotations?sort=-created_at    (descending)
GET /api/v1/quotations?sort=total          (ascending)
```

### Including Relations

```
GET /api/v1/quotations?include=contact,items,createdBy
```

### Searching

```
GET /api/v1/quotations?search=MCB-16A
```

---

## Error Handling

### Business Rule Errors (422)

```php
// In service
throw new InvalidArgumentException('Quotation tidak dapat dikonversi.');

// In controller
try {
    $invoice = $this->quotationService->convertToInvoice($quotation);
    return new InvoiceResource($invoice);
} catch (InvalidArgumentException $e) {
    return response()->json(['message' => $e->getMessage()], 422);
}
```

### Authorization Errors (403)

```php
// Using policies
$this->authorize('approve', $quotation);
```

---

## Route List by Module

### Authentication
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/user`

### Accounts
- `GET|POST /api/v1/accounts`
- `GET|PUT|DELETE /api/v1/accounts/{id}`

### Contacts
- `GET|POST /api/v1/contacts`
- `GET|PUT|DELETE /api/v1/contacts/{id}`

### Quotations
- `GET|POST /api/v1/quotations`
- `GET|PUT|DELETE /api/v1/quotations/{id}`
- `POST /api/v1/quotations/{id}/submit`
- `POST /api/v1/quotations/{id}/approve`
- `POST /api/v1/quotations/{id}/convert`

### Invoices
- `GET|POST /api/v1/invoices`
- `GET|PUT|DELETE /api/v1/invoices/{id}`
- `POST /api/v1/invoices/{id}/post`

### Manufacturing (feature:manufacturing)
- `GET|POST /api/v1/boms`
- `GET|PUT|DELETE /api/v1/boms/{id}`
- `GET|POST /api/v1/work-orders`
- `POST /api/v1/work-orders/{id}/start`
- `POST /api/v1/work-orders/{id}/complete`

### MRP (feature:mrp)
- `GET /api/v1/mrp`
- `POST /api/v1/mrp/run`
- `GET /api/v1/mrp/suggestions`

---

## Related Documentation

- [ADR-0004: Sanctum Authentication](../08-adr/0004-sanctum-authentication.md)
- [ADR-0034: API Versioning](../08-adr/0034-api-versioning.md)
- [ADR-0036: Error Response Format](../08-adr/0036-error-response-format.md)
- [Service Layer](./service-layer.md)
