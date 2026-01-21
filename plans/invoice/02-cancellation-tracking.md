# Phase 2: Add Cancellation Tracking Fields

> **Type**: FIX
> **Status**: 📋 READY TO IMPLEMENT
> **Priority**: Low
> **Effort**: Small (1-2 hours)

## Problem

When an invoice is voided/cancelled:
- Status changes to `Cancelled`
- Reason is passed to `InvoiceVoided` event only
- Event data is ephemeral (only captured by listeners)

Cannot easily answer: "Who cancelled this invoice and why?"

### Current Code

```php
// app/Services/Sales/InvoiceService.php:242
$this->dispatch(InvoiceVoided::fromInvoice($invoice, $this->getUserId(), $reason));
// Reason only in event, not persisted on model
```

### User Story

> As an auditor, I want to see who cancelled an invoice and why directly on the invoice record, so I can review cancellation decisions without searching event logs.

---

## Solution

Add tracking fields to `invoices` table, similar to quotation cancellation tracking.

---

## Implementation

### 1. Migration

```php
// database/migrations/xxxx_add_cancellation_tracking_to_invoices.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancel_reason']);
        });
    }
};
```

### 2. Model Update

```php
// app/Models/Sales/Invoice.php

protected $fillable = [
    // ... existing fields
    'cancelled_at',
    'cancelled_by',
    'cancel_reason',
];

protected function casts(): array
{
    return [
        // ... existing casts
        'cancelled_at' => 'datetime',
    ];
}

/**
 * User who cancelled the invoice.
 */
public function cancelledByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'cancelled_by');
}
```

### 3. Service Update

```php
// app/Services/Sales/InvoiceService.php

public function void(Invoice $invoice, string $reason): ServiceResult
{
    return $this->executeInTransaction('void', function () use ($invoice, $reason) {
        if (! $invoice->stateMachine()->canCancel()) {
            throw StateTransitionException::actionNotAvailable(
                'void',
                $invoice->status->label()
            );
        }

        // Reverse journal entry if exists
        if ($invoice->journal_entry_id && $invoice->journalEntry) {
            $this->journalService->reverseEntry($invoice->journalEntry);
        }

        // ADD: Track cancellation details
        $invoice->cancelled_at = now();
        $invoice->cancelled_by = $this->getUserId();
        $invoice->cancel_reason = $reason;
        $invoice->save();

        // Transition status
        $invoice->transitionTo(DocumentStatus::Cancelled, $this->getUserId());

        // Dispatch event
        $this->dispatch(InvoiceVoided::fromInvoice($invoice, $this->getUserId(), $reason));

        return ServiceResult::success(
            $this->loadRelations($invoice),
            'Faktur berhasil dibatalkan.'
        );
    }, ['invoice_id' => $invoice->id, 'reason' => $reason]);
}
```

### 4. API Resource Update

```php
// app/Http/Resources/Api/V1/InvoiceResource.php

public function toArray(Request $request): array
{
    return [
        // ... existing fields

        // Cancellation tracking (only if cancelled)
        'cancellation' => $this->when(
            $this->status === DocumentStatus::Cancelled,
            fn () => [
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'cancelled_by' => $this->cancelled_by,
                'cancelled_by_name' => $this->cancelledByUser?->name,
                'reason' => $this->cancel_reason,
            ]
        ),
    ];
}
```

---

## Tests

```php
// tests/Feature/Services/Sales/InvoiceCancellationTrackingTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(\App\Services\Sales\InvoiceService::class);
});

it('tracks cancellation details when voiding invoice', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create();

    $result = $this->service->void($invoice, 'Customer requested cancellation');

    $invoice->refresh();

    expect($invoice)
        ->status->toBe(DocumentStatus::Cancelled)
        ->cancel_reason->toBe('Customer requested cancellation')
        ->cancelled_at->not->toBeNull()
        ->cancelled_by->toBe($this->user->id);
});

it('exposes cancellation in API resource', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create();

    $this->service->void($invoice, 'Test cancellation');

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonPath('data.cancellation.reason', 'Test cancellation')
        ->assertJsonPath('data.cancellation.cancelled_by', $this->user->id);
});

it('does not expose cancellation for non-cancelled invoices', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create();

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonMissing(['cancellation']);
});

it('loads cancelled_by relationship', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create();

    $this->service->void($invoice, 'Test');

    $invoice->refresh()->load('cancelledByUser');

    expect($invoice->cancelledByUser)
        ->not->toBeNull()
        ->name->toBe($this->user->name);
});
```

---

## Verification

```bash
# Run migration
php artisan migrate

# Run tests
php artisan test --filter=InvoiceCancellationTracking

# Verify columns exist
php artisan tinker --execute="
    Schema::hasColumn('invoices', 'cancelled_at')
"

# Export API docs
php artisan scramble:export --path=api.json
```

---

## Checklist

- [ ] Migration created
- [ ] Migration run
- [ ] Model fillable updated
- [ ] Model cast added
- [ ] Model relationship added
- [ ] Service populates fields on void
- [ ] API Resource includes cancellation data
- [ ] Tests written and passing
- [ ] API docs exported

---

## Rollback

```bash
php artisan migrate:rollback --step=1
```

Remove from Model:
- `cancelled_at`, `cancelled_by`, `cancel_reason` from fillable
- `cancelled_at` from casts
- `cancelledByUser()` relationship

Remove from InvoiceResource:
- `cancellation` field
