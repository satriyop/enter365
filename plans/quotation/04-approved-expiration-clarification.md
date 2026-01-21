# Phase 4: Clarify Approved Expiration Behavior

> **Type**: FIX (Documentation + Code Change)
> **Status**: ✅ COMPLETE
> **Priority**: Medium
> **Effort**: Small (1-2 hours)
> **Completed**: 2026-01-21
> **Decision**: Option C (sent_at tracking) implemented
> **Tests**: 18 passed (33 assertions)

## Problem

Approved quotations can expire silently. The business intent is unclear:

- **Scenario A**: Approved = internal approval only, customer hasn't seen it → expiration is appropriate
- **Scenario B**: Approved = sent to customer, they may accept → expiration loses potential deals

### Current Behavior

```php
// QuotationStateMachine allows:
Approved → Expired (when valid_until < today)

// markExpired() processes:
Draft OR Submitted → Expired (skips Approved)
```

Wait, let me verify the current implementation...

### Actual Current Behavior

Looking at `QuotationService::markExpired()`:

```php
public function markExpired(): int
{
    Quotation::query()
        ->whereIn('status', self::EXPIRABLE_STATUSES)
        ->where('valid_until', '<', now()->startOfDay())
        ->chunkById(100, function ($quotations) use (&$count) {
            // ...
        });
}

// Need to check what EXPIRABLE_STATUSES contains
```

## Decision Required

**Business Question**: Should Approved quotations auto-expire?

### Option A: Yes, Expire Approved (Default/Current)

**Rationale**: Approved = internal approval only. Customer may not have seen it yet. If validity period passes, it should expire.

**Implementation**: Ensure `EXPIRABLE_STATUSES` includes `Approved`

```php
private const EXPIRABLE_STATUSES = [
    DocumentStatus::Draft,
    DocumentStatus::Submitted,
    DocumentStatus::Approved, // Include
];
```

### Option B: No, Protect Approved

**Rationale**: Approved quotations represent potential closed deals. They shouldn't silently expire. Sales should manually handle.

**Implementation**: Remove `Approved` from expirable statuses

```php
private const EXPIRABLE_STATUSES = [
    DocumentStatus::Draft,
    DocumentStatus::Submitted,
    // Approved NOT included - manual handling required
];
```

### Option C: Add "Sent" Tracking (Recommended - Phase 6)

**Rationale**: The real question is "has customer seen it?" Add tracking for this.

**Implementation**:
- Add `sent_at` timestamp (quick) or full `Sent` status (Phase 6)
- Expire only if not sent to customer
- Sent quotations don't auto-expire

```php
private const EXPIRABLE_STATUSES = [
    DocumentStatus::Draft,
    DocumentStatus::Submitted,
    DocumentStatus::Approved, // Only if not sent
];

// In markExpired():
->where(function ($query) {
    $query->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Submitted])
          ->orWhere(function ($q) {
              $q->where('status', DocumentStatus::Approved)
                ->whereNull('sent_at'); // Only expire unsent Approved
          });
})
```

## Quick Fix: Add sent_at Field

If Option C preferred but Phase 6 is deferred:

### 1. Migration

```php
// database/migrations/xxxx_add_sent_at_to_quotations.php

public function up(): void
{
    Schema::table('quotations', function (Blueprint $table) {
        $table->timestamp('sent_at')->nullable()->after('approved_at');
        $table->foreignId('sent_by')->nullable()->after('sent_at')
            ->constrained('users')->nullOnDelete();
        $table->string('sent_to_email')->nullable()->after('sent_by');
    });
}
```

### 2. Service Method

```php
// app/Services/Sales/QuotationService.php

/**
 * Mark quotation as sent to customer (without changing status).
 */
public function markAsSent(Quotation $quotation, ?string $email = null): ServiceResult
{
    if ($quotation->status !== DocumentStatus::Approved) {
        return ServiceResult::failure('Only approved quotations can be marked as sent.');
    }

    $quotation->update([
        'sent_at' => now(),
        'sent_by' => auth()->id(),
        'sent_to_email' => $email ?? $quotation->contact->email,
    ]);

    return ServiceResult::success($quotation, 'Quotation marked as sent.');
}
```

### 3. Update markExpired()

```php
// app/Services/Sales/QuotationService.php

public function markExpired(): int
{
    $count = 0;

    Quotation::query()
        ->where('valid_until', '<', now()->startOfDay())
        ->where(function ($query) {
            // Draft and Submitted always expirable
            $query->whereIn('status', [
                DocumentStatus::Draft,
                DocumentStatus::Submitted,
            ])
            // Approved only if NOT sent
            ->orWhere(function ($q) {
                $q->where('status', DocumentStatus::Approved)
                  ->whereNull('sent_at');
            });
        })
        ->chunkById(100, function ($quotations) use (&$count) {
            foreach ($quotations as $quotation) {
                try {
                    $stateMachine = $quotation->stateMachine();
                    if ($stateMachine->canTransitionTo(DocumentStatus::Expired)) {
                        $quotation->transitionTo(DocumentStatus::Expired);
                        $count++;
                    }
                } catch (\Exception $e) {
                    report($e);
                }
            }
        });

    return $count;
}
```

### 4. API Endpoint

```php
// routes/api.php
Route::post('quotations/{quotation}/mark-sent', [QuotationController::class, 'markAsSent']);

// app/Http/Controllers/Api/V1/QuotationController.php
public function markAsSent(Request $request, Quotation $quotation)
{
    $result = $this->quotationService->markAsSent(
        $quotation,
        $request->input('email')
    );

    if ($result->failed()) {
        return response()->json(['message' => $result->message], 422);
    }

    return new QuotationResource($result->data);
}
```

## Tests

```php
// tests/Feature/Services/Sales/QuotationExpirationBehaviorTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('expires draft quotations past valid_until', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Draft,
        'valid_until' => now()->subDay(),
    ]);

    app(\App\Services\Sales\QuotationService::class)->markExpired();

    expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
});

it('expires submitted quotations past valid_until', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Submitted,
        'valid_until' => now()->subDay(),
    ]);

    app(\App\Services\Sales\QuotationService::class)->markExpired();

    expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
});

// Tests for Option C (with sent_at tracking)
it('expires unsent approved quotations', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => now()->subDay(),
        'sent_at' => null, // Not sent
    ]);

    app(\App\Services\Sales\QuotationService::class)->markExpired();

    expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
});

it('does not expire sent approved quotations', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => now()->subDay(),
        'sent_at' => now()->subWeek(), // Was sent
    ]);

    app(\App\Services\Sales\QuotationService::class)->markExpired();

    expect($quotation->fresh()->status)->toBe(DocumentStatus::Approved); // Unchanged
});

it('can mark approved quotation as sent', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $quotation = Quotation::factory()->approved()->create();

    $result = app(\App\Services\Sales\QuotationService::class)
        ->markAsSent($quotation, 'customer@example.com');

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh())
        ->sent_at->not->toBeNull()
        ->sent_to_email->toBe('customer@example.com');
});
```

## Documentation Update

Regardless of implementation choice, document the behavior:

```php
// In QuotationService or README

/**
 * Expiration Behavior:
 *
 * The following quotations auto-expire when past valid_until date:
 * - Draft: Always expires
 * - Submitted: Always expires
 * - Approved: Expires only if NOT marked as sent to customer
 *
 * To prevent an Approved quotation from expiring:
 * - Mark it as sent using markAsSent()
 * - Or convert it to invoice before expiration
 *
 * Quotations that NEVER auto-expire:
 * - Converted: Already closed
 * - Cancelled: Already closed
 * - Expired: Already expired
 */
```

## Verification

```bash
# Run expiration tests
php artisan test --filter=QuotationExpiration

# Check current expirable statuses
grep -r "EXPIRABLE_STATUSES" app/Services/Sales/

# Check how many approved quotations might expire
php artisan tinker --execute="
    App\Models\Sales\Quotation::where('status', 'approved')
        ->where('valid_until', '<', now())
        ->count()
"
```

## Checklist

- [x] Business decision made: **Option C** (sent_at tracking)
- [x] Document the chosen behavior (in code comments)
- [x] Add migration for sent_at, sent_by, sent_to_email, sent_via
- [x] Add markAsSent() method to QuotationService
- [x] Add isSent() helper to Quotation model
- [x] Add sender() relationship to Quotation model
- [x] Update markExpired() to skip sent Approved quotations
- [x] Add API endpoint POST /quotations/{id}/mark-sent
- [x] Tests written and passing (18 tests, 33 assertions)
- [x] Update API docs (`php artisan scramble:export --path=api.json`)

## Decision Log

| Date | Decision | Rationale | Decided By |
|------|----------|-----------|------------|
| 2026-01-21 | Option C | Best balance - track sent_at to distinguish internal vs sent quotations | Auto (fresh dev app) |

---

## Summary of Options

| Option | Approved Expires? | Complexity | Recommendation |
|--------|-------------------|------------|----------------|
| **A** | Always | None | Simple, may lose deals |
| **B** | Never | None | Simple, needs manual handling |
| **C** | Only if unsent | Small migration | Best balance ✓ |
