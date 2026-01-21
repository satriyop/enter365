# Phase 4: Add is_overdue Flag

> **Type**: FIX
> **Status**: 📋 READY TO IMPLEMENT
> **Priority**: Medium
> **Effort**: Small (1-2 hours)

## Problem

An invoice can simultaneously be:
- Partially paid (`paid_amount > 0` but `< total_amount`)
- Past due (`due_date` in the past)

The current system only allows ONE status. The `updatePaymentStatus()` method prioritizes payment status:

```php
// app/Services/Sales/InvoiceService.php:360-376

// Priority order:
1. paid_amount >= total_amount → Paid
2. paid_amount > 0 → Partial      // ← Wins over overdue!
3. due_date.isPast() → Overdue
```

This means a partially-paid overdue invoice shows as "Partial" with no indication it's past due.

### User Story

> As a collections manager, I want to see which invoices are overdue regardless of their payment status, so I can prioritize follow-up calls for aging receivables.

---

## Solution

Add `is_overdue` boolean flag separate from status.
Update on every status change and via scheduler.

This allows reporting: "All Partial invoices that are also overdue"

---

## Implementation

### 1. Migration

```php
// database/migrations/xxxx_add_is_overdue_flag_to_invoices.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_overdue')->default(false)->after('status');
            $table->index('is_overdue');
        });

        // Backfill existing data
        DB::statement("
            UPDATE invoices
            SET is_overdue = CASE
                WHEN due_date < CURDATE()
                    AND status NOT IN ('paid', 'cancelled', 'draft')
                THEN true
                ELSE false
            END
        ");
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['is_overdue']);
            $table->dropColumn('is_overdue');
        });
    }
};
```

### 2. Model Update

```php
// app/Models/Sales/Invoice.php

protected $fillable = [
    // ... existing fields
    'is_overdue',
];

protected function casts(): array
{
    return [
        // ... existing casts
        'is_overdue' => 'boolean',
    ];
}

/**
 * Scope for overdue invoices (using flag).
 */
public function scopeOverdue(Builder $query): Builder
{
    return $query->where('is_overdue', true);
}

/**
 * Scope for not overdue invoices.
 */
public function scopeNotOverdue(Builder $query): Builder
{
    return $query->where('is_overdue', false);
}

/**
 * Update the is_overdue flag based on current state.
 */
public function updateOverdueFlag(): void
{
    $wasOverdue = $this->is_overdue;
    $isNowOverdue = $this->calculateIsOverdue();

    if ($wasOverdue !== $isNowOverdue) {
        $this->is_overdue = $isNowOverdue;
        $this->saveQuietly(); // Don't trigger events
    }
}

/**
 * Calculate if invoice should be marked overdue.
 */
private function calculateIsOverdue(): bool
{
    // Not applicable for terminal/draft states
    if (in_array($this->status, [
        DocumentStatus::Paid,
        DocumentStatus::Cancelled,
        DocumentStatus::Draft,
    ])) {
        return false;
    }

    return $this->due_date->isPast();
}
```

### 3. Service Updates

```php
// app/Services/Sales/InvoiceService.php

// Add to post() method after status transition:
$invoice->updateOverdueFlag();

// Add to updatePaymentStatus() method after status transition:
$invoice->updateOverdueFlag();

// Add to markAsOverdue() method:
public function markAsOverdue(Invoice $invoice): ServiceResult
{
    return $this->executeInTransaction('mark_overdue', function () use ($invoice) {
        if (! $invoice->stateMachine()->canMarkAsOverdue()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_overdue',
                $invoice->status->label()
            );
        }

        $invoice->transitionTo(DocumentStatus::Overdue, $this->getUserId());
        $invoice->is_overdue = true; // Explicitly set flag
        $invoice->save();

        $this->dispatch(InvoiceOverdue::fromInvoice($invoice));

        return ServiceResult::success(
            $this->loadRelations($invoice),
            'Faktur ditandai sebagai jatuh tempo.'
        );
    }, ['invoice_id' => $invoice->id]);
}
```

### 4. Update Scheduler Command

```php
// app/Console/Commands/MarkOverdueInvoicesCommand.php

// Add to handle():
// Also update is_overdue flag for all invoices (not just status)
Invoice::query()
    ->whereNotIn('status', [
        DocumentStatus::Paid->value,
        DocumentStatus::Cancelled->value,
        DocumentStatus::Draft->value,
    ])
    ->where('due_date', '<', now()->startOfDay())
    ->where('is_overdue', false)
    ->update(['is_overdue' => true]);

// Clear flag for invoices no longer overdue (edge case: due date extended)
Invoice::query()
    ->where('due_date', '>=', now()->startOfDay())
    ->where('is_overdue', true)
    ->update(['is_overdue' => false]);
```

### 5. API Resource Update

```php
// app/Http/Resources/Api/V1/InvoiceResource.php

public function toArray(Request $request): array
{
    return [
        // ... existing fields

        'status' => [
            'value' => $this->status->value,
            'label' => $this->status->label(),
            'color' => $this->status->color(),
            'is_terminal' => $this->status->isTerminal(),
            'is_editable' => $this->status->isEditable(),
        ],

        // ADD: Separate overdue indicator
        'is_overdue' => $this->is_overdue,
        'days_overdue' => $this->is_overdue ? $this->getDaysOverdue() : 0,

        // ... rest of fields
    ];
}
```

### 6. Filter Update

```php
// app/Filters/InvoiceFilter.php

// Add filter for is_overdue
protected function isOverdue(bool $value): Builder
{
    return $this->builder->where('is_overdue', $value);
}
```

---

## Tests

```php
// tests/Feature/Services/Sales/InvoiceOverdueFlagTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sets is_overdue flag when invoice becomes overdue', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now()->subDays(5),
            'is_overdue' => false,
        ]);

    $invoice->updateOverdueFlag();

    expect($invoice->is_overdue)->toBeTrue();
});

it('clears is_overdue flag when invoice is paid', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Partial,
            'due_date' => now()->subDays(5),
            'is_overdue' => true,
            'total_amount' => 1000000,
            'paid_amount' => 500000,
        ]);

    // Simulate full payment
    $invoice->status = DocumentStatus::Paid;
    $invoice->paid_amount = 1000000;
    $invoice->updateOverdueFlag();

    expect($invoice->is_overdue)->toBeFalse();
});

it('partial invoice can have is_overdue true', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Partial,
            'due_date' => now()->subDays(10),
            'total_amount' => 1000000,
            'paid_amount' => 500000,
        ]);

    $invoice->updateOverdueFlag();

    expect($invoice)
        ->status->toBe(DocumentStatus::Partial)
        ->is_overdue->toBeTrue();
});

it('sent invoice can have is_overdue true', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now()->subDays(3),
        ]);

    $invoice->updateOverdueFlag();

    expect($invoice)
        ->status->toBe(DocumentStatus::Sent)
        ->is_overdue->toBeTrue();
});

it('draft invoice never has is_overdue true', function () {
    $invoice = Invoice::factory()->draft()->create([
        'due_date' => now()->subDays(30),
    ]);

    $invoice->updateOverdueFlag();

    expect($invoice->is_overdue)->toBeFalse();
});

it('cancelled invoice never has is_overdue true', function () {
    $invoice = Invoice::factory()->create([
        'status' => DocumentStatus::Cancelled,
        'due_date' => now()->subDays(30),
        'is_overdue' => true, // Even if somehow set
    ]);

    $invoice->updateOverdueFlag();

    expect($invoice->is_overdue)->toBeFalse();
});

// Scope tests
it('scopes to overdue invoices', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->subDays(5),
        'is_overdue' => true,
    ]);
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(30),
        'is_overdue' => false,
    ]);

    expect(Invoice::overdue()->count())->toBe(1);
    expect(Invoice::notOverdue()->count())->toBe(1);
});

// API test
it('includes is_overdue in API response', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now()->subDays(5),
            'is_overdue' => true,
        ]);

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonPath('data.is_overdue', true)
        ->assertJsonPath('data.days_overdue', 5);
});

// Filter test
it('can filter by is_overdue', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    Invoice::factory()->sent()->create(['is_overdue' => true]);
    Invoice::factory()->sent()->create(['is_overdue' => false]);

    $response = $this->getJson('/api/v1/invoices?is_overdue=true');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});
```

---

## Verification

```bash
# Run migration
php artisan migrate

# Verify backfill worked
php artisan tinker --execute="
    echo 'Overdue invoices: ' . App\Models\Sales\Invoice::where('is_overdue', true)->count();
"

# Run tests
php artisan test --filter=InvoiceOverdueFlag

# Test API filter
curl -X GET 'http://localhost/api/v1/invoices?is_overdue=true'

# Export API docs
php artisan scramble:export --path=api.json
```

---

## Checklist

- [ ] Migration created with backfill
- [ ] Migration run
- [ ] Model fillable updated
- [ ] Model cast added
- [ ] Model scopes added
- [ ] Model `updateOverdueFlag()` method added
- [ ] Service methods update flag
- [ ] Scheduler updates flags
- [ ] API Resource includes `is_overdue`
- [ ] Filter supports `is_overdue`
- [ ] Tests written and passing
- [ ] API docs exported

---

## Example Queries After Implementation

```php
// All overdue invoices (regardless of status)
Invoice::overdue()->get();

// Partial invoices that are overdue (priority for collection)
Invoice::where('status', DocumentStatus::Partial)
    ->overdue()
    ->orderBy('due_date')
    ->get();

// Dashboard: Overdue amount by status
Invoice::overdue()
    ->selectRaw('status, SUM(total_amount - paid_amount) as overdue_amount')
    ->groupBy('status')
    ->get();
```

---

## Rollback

```bash
php artisan migrate:rollback --step=1
```

Remove from Model:
- `is_overdue` from fillable
- `is_overdue` from casts
- `scopeOverdue()`, `scopeNotOverdue()` methods
- `updateOverdueFlag()`, `calculateIsOverdue()` methods
