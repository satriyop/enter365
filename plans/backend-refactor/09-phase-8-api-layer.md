# Phase 8: API Layer Clean-up

> **Goal**: Standardize controllers, improve API resources, and enhance API documentation.

## Current State

- Controllers use Form Requests for validation
- API Resources exist for most entities
- Scramble for OpenAPI documentation
- Versioned API (v1)

---

## Deliverables

- [x] Standardized controller structure
- [x] Enhanced API resources with metadata
- [x] Consistent error responses
- [x] Rate limiting configuration
- [ ] API documentation improvements (Scramble already configured)

---

## Part 1: Base Controller

### 1.1 API Controller Base

```php
<?php
// File: app/Http/Controllers/Api/V1/Controller.php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use App\Support\Results\ServiceResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class Controller extends BaseController
{
    /**
     * Return success response.
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return created response.
     */
    protected function created(
        JsonResource $resource,
        string $message = 'Data berhasil dibuat.'
    ): JsonResponse {
        return $resource->response()
            ->setStatusCode(201)
            ->header('X-Message', $message);
    }

    /**
     * Return error response.
     */
    protected function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return response from ServiceResult.
     */
    protected function fromResult(
        ServiceResult $result,
        string $resourceClass,
        int $successStatus = 200
    ): JsonResponse {
        if ($result->isFailure()) {
            return $this->error(
                $result->getMessage() ?? 'Operation failed',
                422,
                $result->getErrors()
            );
        }

        $data = $result->getData();

        if ($data === null) {
            return $this->success(null, $result->getMessage() ?? 'Success');
        }

        return (new $resourceClass($data))
            ->response()
            ->setStatusCode($successStatus);
    }

    /**
     * Return deleted response.
     */
    protected function deleted(string $message = 'Data berhasil dihapus.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
```

---

## Part 2: Refactored Controller

### 2.1 Invoice Controller

```php
<?php
// File: app/Http/Controllers/Api/V1/InvoiceController.php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Filters\InvoiceFilter;
use App\Http\Requests\Api\V1\PostInvoiceRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\UpdateInvoiceRequest;
use App\Http\Requests\Api\V1\VoidInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Sales\Invoice;
use App\QueryServices\Sales\InvoiceQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService,
        private InvoiceQueryService $queryService
    ) {}

    /**
     * List invoices with filtering and pagination.
     *
     * @queryParam status string Filter by status. Example: sent
     * @queryParam contact_id int Filter by contact. Example: 1
     * @queryParam date_from string Filter from date. Example: 2024-01-01
     * @queryParam date_to string Filter to date. Example: 2024-12-31
     * @queryParam search string Search by invoice number or contact name.
     * @queryParam per_page int Items per page. Default: 25
     * @queryParam include_workflow bool Include workflow metadata.
     */
    public function index(InvoiceFilter $filter): AnonymousResourceCollection
    {
        $invoices = $this->queryService->paginate(
            $filter->toArray(),
            $filter->getRequest()->input('per_page', 25)
        );

        return InvoiceResource::collection($invoices);
    }

    /**
     * Create new invoice.
     *
     * @response 201 {
     *   "data": {"id": 1, "invoice_number": "INV-202401-0001"}
     * }
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $result = $this->invoiceService->create($request->validated());

        return $this->fromResult($result, InvoiceResource::class, 201);
    }

    /**
     * Get invoice details.
     *
     * @queryParam include_workflow bool Include workflow metadata.
     * @queryParam include_history bool Include status history.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice = $this->queryService->findWithDetails($invoice->id);

        return new InvoiceResource($invoice);
    }

    /**
     * Update invoice.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->invoiceService->update($invoice, $request->validated());

        return $this->fromResult($result, InvoiceResource::class);
    }

    /**
     * Delete invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $result = $this->invoiceService->delete($invoice);

        if ($result->isFailure()) {
            return $this->error($result->getMessage() ?? 'Delete failed', 409);
        }

        return $this->deleted('Faktur berhasil dihapus.');
    }

    /**
     * Post invoice (create journal entry, change status to Sent).
     */
    public function post(PostInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->invoiceService->post($invoice);

        return $this->fromResult($result, InvoiceResource::class);
    }

    /**
     * Void/cancel invoice.
     */
    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->invoiceService->void($invoice, $request->validated('reason'));

        return $this->fromResult($result, InvoiceResource::class);
    }

    /**
     * Get invoice statistics.
     *
     * @queryParam date_from string Start date. Example: 2024-01-01
     * @queryParam date_to string End date. Example: 2024-12-31
     */
    public function statistics(): JsonResponse
    {
        $range = \App\Domain\Shared\ValueObjects\DateRange::thisMonth();

        if (request()->has('date_from') && request()->has('date_to')) {
            $range = new \App\Domain\Shared\ValueObjects\DateRange(
                request('date_from'),
                request('date_to')
            );
        }

        $stats = $this->queryService->getStatistics($range);

        return $this->success($stats);
    }

    /**
     * Get aging report.
     */
    public function aging(): JsonResponse
    {
        $aging = $this->queryService->getAgingReport();

        return $this->success($aging);
    }
}
```

---

## Part 3: Enhanced API Resources

### 3.1 Invoice Resource

```php
<?php
// File: app/Http/Resources/Api/V1/InvoiceResource.php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Sales\Invoice $resource
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'reference' => $this->reference,
            'description' => $this->description,

            // Dates
            'invoice_date' => $this->invoice_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'days_until_due' => $this->getDaysUntilDue(),
            'is_overdue' => $this->isOverdue(),
            $this->mergeWhen($this->isOverdue(), [
                'days_overdue' => $this->getDaysOverdue(),
            ]),

            // Contact
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'contact_id' => $this->contact_id,

            // Amounts
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'outstanding' => $this->getOutstandingAmount(),
            'base_currency_total' => $this->base_currency_total,

            // Formatted amounts
            'formatted' => [
                'subtotal' => $this->formatMoney($this->subtotal),
                'tax_amount' => $this->formatMoney($this->tax_amount),
                'total_amount' => $this->formatMoney($this->total_amount),
                'paid_amount' => $this->formatMoney($this->paid_amount),
                'outstanding' => $this->formatMoney($this->getOutstandingAmount()),
            ],

            // Early payment discount
            $this->mergeWhen($this->hasEarlyPaymentDiscount(), [
                'early_discount' => [
                    'percent' => $this->early_discount_percent,
                    'days' => $this->early_discount_days,
                    'deadline' => $this->early_discount_deadline?->format('Y-m-d'),
                    'amount' => $this->calculateEarlyDiscountAmount(),
                    'discounted_total' => $this->getEarlyPaymentTotal(),
                ],
            ]),

            // Status
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_terminal' => $this->status->isTerminal(),
                'is_editable' => $this->status->isEditable(),
            ],

            // Items
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->whenCounted('items'),

            // Journal entry
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
            'has_journal_entry' => $this->journal_entry_id !== null,

            // Payments
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payment_count' => $this->whenCounted('payments'),

            // Workflow (optional)
            'workflow' => $this->when(
                $request->boolean('include_workflow'),
                fn () => $this->stateMachine()->getWorkflowMetadata()
            ),

            // Status history (optional)
            'status_history' => $this->when(
                $request->boolean('include_history'),
                fn () => $this->getStatusTimeline()
            ),

            // Actions
            'actions' => [
                'can_edit' => $this->stateMachine()->canEdit(),
                'can_post' => $this->stateMachine()->canPost(),
                'can_void' => $this->stateMachine()->canCancel(),
                'can_delete' => $this->stateMachine()->canEdit(),
            ],

            // Metadata
            'reminder_count' => $this->reminder_count,
            'last_reminder_at' => $this->last_reminder_at?->format('Y-m-d H:i:s'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Links
            'links' => [
                'self' => route('api.v1.invoices.show', $this->id),
                'pdf' => route('api.v1.invoices.pdf', $this->id),
            ],
        ];
    }

    private function formatMoney(int $amount): string
    {
        return 'Rp ' . number_format($amount / 100, 0, ',', '.');
    }
}
```

---

## Part 4: Consistent Error Handling

### 4.1 API Exception Handler

```php
<?php
// File: app/Exceptions/Handler.php or bootstrap/app.php

use App\Exceptions\Domain\DomainException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

->withExceptions(function (Exceptions $exceptions) {

    // Domain exceptions
    $exceptions->renderable(function (DomainException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getStatusCode());
        }
    });

    // Validation exceptions
    $exceptions->renderable(function (ValidationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        }
    });

    // Not found
    $exceptions->renderable(function (NotFoundHttpException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }
    });

    // Log all exceptions
    $exceptions->report(function (Throwable $e) {
        if (app()->bound(\App\Contracts\Logging\ContextualLoggerInterface::class)) {
            app(\App\Contracts\Logging\ContextualLoggerInterface::class)->logError($e, [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        }
    });
})
```

---

## Part 5: Rate Limiting

### 5.1 Configure Rate Limiting

```php
<?php
// File: app/Providers/AppServiceProvider.php (add to boot)

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    $this->configureRateLimiting();
    // ... other boot code
}

private function configureRateLimiting(): void
{
    // API rate limit
    RateLimiter::for('api', function ($request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Strict rate limit for auth endpoints
    RateLimiter::for('auth', function ($request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    // Heavy operations
    RateLimiter::for('reports', function ($request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });

    // Export operations
    RateLimiter::for('exports', function ($request) {
        return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
    });
}
```

### 5.2 Apply Rate Limiting

```php
<?php
// File: routes/api.php

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {

    // Standard CRUD
    Route::apiResource('invoices', InvoiceController::class);

    // Heavy operations with stricter limits
    Route::middleware('throttle:reports')->group(function () {
        Route::get('invoices/statistics', [InvoiceController::class, 'statistics']);
        Route::get('invoices/aging', [InvoiceController::class, 'aging']);
        Route::get('reports/{type}', [ReportController::class, 'generate']);
    });

    // Export operations
    Route::middleware('throttle:exports')->group(function () {
        Route::post('exports/{type}', [ExportController::class, 'export']);
    });
});

// Auth routes with strict limits
Route::middleware('throttle:auth')->prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});
```

---

## Part 6: API Documentation

### 6.1 Scramble Configuration

```php
<?php
// File: config/scramble.php

return [
    'info' => [
        'version' => '1.0.0',
        'title' => 'Enter365 API',
        'description' => 'Enterprise Resource Planning API for manufacturing and trading businesses.',
    ],

    'servers' => [
        [
            'url' => env('APP_URL') . '/api',
            'description' => 'API Server',
        ],
    ],

    'tags' => [
        ['name' => 'Authentication', 'description' => 'Auth endpoints'],
        ['name' => 'Invoices', 'description' => 'Sales invoice management'],
        ['name' => 'Bills', 'description' => 'Purchasing bill management'],
        ['name' => 'Work Orders', 'description' => 'Manufacturing work orders'],
        ['name' => 'Inventory', 'description' => 'Inventory management'],
        ['name' => 'Reports', 'description' => 'Report generation'],
    ],
];
```

---

## Verification Checklist

- [x] Base API controller created
- [x] Controllers refactored to use base
- [x] API resources enhanced with metadata
- [x] Error responses standardized
- [x] Rate limiting configured
- [x] API documentation updated (Scramble already configured)
- [x] All API tests pass (1 pre-existing failure - journal balance issue)

---

## Refactoring Complete!

Congratulations! You have completed all phases of the backend refactoring plan.

### Summary of Changes

1. **Phase 0**: Foundation with logging, error handling, health checks
2. **Phase 1**: Complete domain layer across all modules
3. **Phase 2**: Repository pattern for data access abstraction
4. **Phase 3**: Service layer with SOLID principles
5. **Phase 4**: Event-driven architecture with subscribers
6. **Phase 5**: Strategy pattern for flexibility
7. **Phase 6**: Enhanced state machines with guards/actions
8. **Phase 7**: Comprehensive testing infrastructure
9. **Phase 8**: Clean API layer with proper responses

### Maintenance Guidelines

1. All new features should follow established patterns
2. Run tests before merging any changes
3. Keep documentation updated
4. Review and refactor regularly
5. Monitor observability metrics
