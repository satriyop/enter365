# Phase 1: Add Cancel Transition

> **Type**: FIX
> **Status**: ✅ COMPLETE
> **Priority**: High
> **Effort**: Small (2-4 hours)
> **Completed**: 2026-01-21
> **Tests**: 20 passed (37 assertions)

## Problem

Users cannot cancel a Submitted or Approved quotation. They must:
- Wait for expiration (impractical)
- Directly manipulate database (dangerous)

### User Story

> As a sales manager, I want to cancel a quotation that the customer no longer needs, so that it doesn't appear in active quotation lists and cannot be accidentally converted.

## Solution

Add `cancel()` method and `Cancelled` status transition to the quotation workflow.

## Implementation

### 1. State Machine Updates

```php
// app/Domain/Sales/Quotations/QuotationStateMachine.php

protected function defineTransitions(): array
{
    return [
        DocumentStatus::Draft->value => [
            DocumentStatus::Submitted,
            DocumentStatus::Cancelled,  // ADD
            DocumentStatus::Expired,
        ],
        DocumentStatus::Submitted->value => [
            DocumentStatus::Approved,
            DocumentStatus::Rejected,
            DocumentStatus::Cancelled,  // ADD
            DocumentStatus::Expired,
        ],
        DocumentStatus::Approved->value => [
            DocumentStatus::Converted,
            DocumentStatus::Cancelled,  // ADD
            DocumentStatus::Expired,
        ],
        DocumentStatus::Rejected->value => [
            DocumentStatus::Draft, // Revise
        ],
        DocumentStatus::Expired->value => [
            DocumentStatus::Draft, // Revise
        ],
        DocumentStatus::Cancelled->value => [  // ADD
            DocumentStatus::Draft, // Revise - allow restarting cancelled quotations
        ],
        DocumentStatus::Converted->value => [],
    ];
}

/**
 * Check if quotation can be cancelled.
 */
public function canCancel(): bool
{
    // Can cancel Draft, Submitted, or Approved
    // Cannot cancel already Cancelled, Converted, or Expired
    return in_array($this->currentStatus, [
        DocumentStatus::Draft,
        DocumentStatus::Submitted,
        DocumentStatus::Approved,
    ]);
}

/**
 * Check if quotation can be revised (includes Cancelled now).
 */
public function canRevise(): bool
{
    return in_array($this->currentStatus, [
        DocumentStatus::Approved,
        DocumentStatus::Rejected,
        DocumentStatus::Expired,
        DocumentStatus::Cancelled,  // ADD
    ]);
}
```

### 2. Add Tracking Fields (Migration)

```php
// database/migrations/xxxx_add_cancellation_tracking_to_quotations.php

public function up(): void
{
    Schema::table('quotations', function (Blueprint $table) {
        $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
        $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
            ->constrained('users')->nullOnDelete();
        $table->text('cancellation_reason')->nullable()->after('cancelled_by');
    });
}

public function down(): void
{
    Schema::table('quotations', function (Blueprint $table) {
        $table->dropForeign(['cancelled_by']);
        $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_reason']);
    });
}
```

### 3. Update Model

```php
// app/Models/Sales/Quotation.php

protected $casts = [
    // ... existing casts
    'cancelled_at' => 'datetime',
];

public function cancelledBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'cancelled_by');
}
```

### 4. Create Event

```php
// app/Domain/Sales/Quotations/Events/QuotationCancelled.php

<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Events;

use App\Models\Sales\Quotation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $quotationId,
        public readonly string $quotationNumber,
        public readonly string $previousStatus,
        public readonly ?string $cancellationReason,
        public readonly ?int $cancelledBy,
        public readonly \DateTimeInterface $cancelledAt,
    ) {}

    public static function fromQuotation(
        Quotation $quotation,
        string $previousStatus,
        ?string $reason = null
    ): self {
        return new self(
            quotationId: $quotation->id,
            quotationNumber: $quotation->quotation_number,
            previousStatus: $previousStatus,
            cancellationReason: $reason,
            cancelledBy: auth()->id(),
            cancelledAt: now(),
        );
    }
}
```

### 5. Service Layer

```php
// app/Services/Sales/QuotationService.php

use App\Domain\Sales\Quotations\Events\QuotationCancelled;

/**
 * Cancel a quotation.
 */
public function cancel(Quotation $quotation, ?string $reason = null): ServiceResult
{
    $stateMachine = $quotation->stateMachine();

    if (!$stateMachine->canCancel()) {
        return ServiceResult::failure(
            'Quotation cannot be cancelled. Only Draft, Submitted, or Approved quotations can be cancelled.'
        );
    }

    return DB::transaction(function () use ($quotation, $reason, $stateMachine) {
        $previousStatus = $quotation->status->value;

        // Update cancellation tracking
        $quotation->cancelled_at = now();
        $quotation->cancelled_by = auth()->id();
        $quotation->cancellation_reason = $reason;

        // Clear any scheduled follow-ups
        $quotation->next_follow_up_at = null;

        // Transition to Cancelled
        $quotation->transitionTo(DocumentStatus::Cancelled);

        // Dispatch event
        event(QuotationCancelled::fromQuotation($quotation, $previousStatus, $reason));

        return ServiceResult::success($quotation, 'Quotation cancelled successfully.');
    });
}
```

### 6. Controller

```php
// app/Http/Controllers/Api/V1/QuotationController.php

use App\Http\Requests\Api\V1\CancelQuotationRequest;

/**
 * Cancel a quotation.
 *
 * @operationId cancelQuotation
 */
public function cancel(CancelQuotationRequest $request, Quotation $quotation)
{
    $result = $this->quotationService->cancel(
        $quotation,
        $request->input('reason')
    );

    if ($result->failed()) {
        return response()->json([
            'message' => $result->message,
        ], 422);
    }

    return new QuotationResource($result->data);
}
```

### 7. Request Validation

```php
// app/Http/Requests/Api/V1/CancelQuotationRequest.php

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CancelQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add policy check if needed
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

### 8. Route

```php
// routes/api.php

Route::prefix('quotations/{quotation}')->group(function () {
    // ... existing routes
    Route::post('cancel', [QuotationController::class, 'cancel']);
});
```

### 9. Event Subscriber Update

```php
// app/Listeners/Sales/QuotationEventSubscriber.php

use App\Domain\Sales\Quotations\Events\QuotationCancelled;

public function subscribe(Dispatcher $events): void
{
    // ... existing subscriptions
    $events->listen(QuotationCancelled::class, [$this, 'onCancelled']);
}

public function onCancelled(QuotationCancelled $event): void
{
    $this->logger->info('Quotation cancelled', [
        'quotation_id' => $event->quotationId,
        'quotation_number' => $event->quotationNumber,
        'previous_status' => $event->previousStatus,
        'reason' => $event->cancellationReason,
        'cancelled_by' => $event->cancelledBy,
    ]);
}
```

## Tests

```php
// tests/Feature/Services/Sales/QuotationCancellationTest.php

<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Events\QuotationCancelled;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->create());
});

it('can cancel draft quotation', function () {
    Event::fake([QuotationCancelled::class]);

    $quotation = Quotation::factory()->create(['status' => DocumentStatus::Draft]);

    $result = app(\App\Services\Sales\QuotationService::class)
        ->cancel($quotation, 'Customer requested cancellation');

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh())
        ->status->toBe(DocumentStatus::Cancelled)
        ->cancellation_reason->toBe('Customer requested cancellation')
        ->cancelled_at->not->toBeNull();

    Event::assertDispatched(QuotationCancelled::class);
});

it('can cancel submitted quotation', function () {
    $quotation = Quotation::factory()
        ->has(\App\Models\Sales\QuotationItem::factory())
        ->submitted()
        ->create();

    $result = app(\App\Services\Sales\QuotationService::class)->cancel($quotation);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('can cancel approved quotation', function () {
    $quotation = Quotation::factory()->approved()->create();

    $result = app(\App\Services\Sales\QuotationService::class)->cancel($quotation);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('cannot cancel converted quotation', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Converted,
        'converted_to_invoice_id' => 1,
    ]);

    $result = app(\App\Services\Sales\QuotationService::class)->cancel($quotation);

    expect($result->failed())->toBeTrue();
    expect($result->message)->toContain('cannot be cancelled');
});

it('cannot cancel already cancelled quotation', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Cancelled,
    ]);

    $result = app(\App\Services\Sales\QuotationService::class)->cancel($quotation);

    expect($result->failed())->toBeTrue();
});

it('cannot cancel expired quotation', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Expired,
    ]);

    $result = app(\App\Services\Sales\QuotationService::class)->cancel($quotation);

    expect($result->failed())->toBeTrue();
});

it('can revise cancelled quotation', function () {
    $quotation = Quotation::factory()
        ->has(\App\Models\Sales\QuotationItem::factory())
        ->create(['status' => DocumentStatus::Cancelled]);

    $result = app(\App\Services\Sales\QuotationService::class)->revise($quotation);

    expect($result->succeeded())->toBeTrue();
    expect($result->data->status)->toBe(DocumentStatus::Draft);
    expect($result->data->original_quotation_id)->toBe($quotation->id);
});

it('clears follow-up on cancellation', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Submitted,
        'next_follow_up_at' => now()->addDays(3),
    ]);

    app(\App\Services\Sales\QuotationService::class)->cancel($quotation);

    expect($quotation->fresh()->next_follow_up_at)->toBeNull();
});

// API Tests
it('can cancel quotation via API', function () {
    $quotation = Quotation::factory()->submitted()->create();

    $response = $this->postJson("/api/v1/quotations/{$quotation->id}/cancel", [
        'reason' => 'Project cancelled by customer',
    ]);

    $response->assertOk();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('returns 422 when cannot cancel', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Converted,
    ]);

    $response = $this->postJson("/api/v1/quotations/{$quotation->id}/cancel");

    $response->assertUnprocessable();
});
```

## Verification

```bash
# Run migration
php artisan migrate

# Run specific tests
php artisan test --filter=QuotationCancellation

# Run all quotation tests
php artisan test --filter=Quotation

# Export updated API docs
php artisan scramble:export --path=api.json
```

## Checklist

- [x] Migration created and run
- [x] Model updated with cast and relationship
- [x] Event created
- [x] State machine updated
- [x] Service method added
- [x] Controller method added
- [x] Request validation created
- [x] Route added
- [x] Event subscriber updated
- [x] Tests written and passing
- [x] API docs exported (`php artisan scramble:export --path=api.json`)

## Rollback

If issues arise:
```bash
php artisan migrate:rollback --step=1
```

Remove added code from:
- QuotationStateMachine
- QuotationService
- QuotationController
- routes/api.php
