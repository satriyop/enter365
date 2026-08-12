<?php

declare(strict_types=1);

use App\Domain\Tax\Events\NsfpAllocated;
use App\Domain\Tax\Events\NsfpLowStock;
use App\Exceptions\Domain\BusinessRuleException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Tax\NsfpRange;
use App\Models\User;
use App\Services\Tax\NsfpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('NsfpService range management', function () {
    it('creates an NSFP range with next_number at range_start', function () {
        $service = app(NsfpService::class);

        $range = $service->createRange([
            'transaction_code' => '010',
            'branch_code' => '000',
            'year_code' => '26',
            'range_start' => 100,
            'range_end' => 500,
            'description' => 'Test range',
        ], $this->user->id);

        expect($range->next_number)->toBe(100)
            ->and($range->used_count)->toBe(0)
            ->and($range->is_active)->toBeTrue()
            ->and($range->created_by)->toBe($this->user->id)
            ->and($range->description)->toBe('Test range');
    });

    it('updates description and active flag', function () {
        $range = NsfpRange::factory()->active()->create();
        $service = app(NsfpService::class);

        $updated = $service->updateRange($range, [
            'description' => 'Updated',
            'is_active' => false,
        ]);

        expect($updated->description)->toBe('Updated')
            ->and($updated->is_active)->toBeFalse();
    });

    it('deactivates a range', function () {
        $range = NsfpRange::factory()->active()->create();
        $service = app(NsfpService::class);

        $deactivated = $service->deactivateRange($range);

        expect($deactivated->is_active)->toBeFalse();
    });
});

describe('NsfpService allocation', function () {
    it('allocates sequential NSFP number to invoice', function () {
        config(['accounting.nsfp.enabled' => true]);

        NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $service = app(NsfpService::class);
        $nsfpNumber = $service->allocate($invoice);

        expect($nsfpNumber)->toBe('010.000-26.00000001');
        expect($invoice->fresh()->nsfp_number)->toBe('010.000-26.00000001');
        expect($invoice->fresh()->nsfp_range_id)->not->toBeNull();
        expect($invoice->fresh()->nsfp_assigned_at)->not->toBeNull();
    });

    it('allocates sequential numbers across multiple invoices', function () {
        config(['accounting.nsfp.enabled' => true]);

        NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        $service = app(NsfpService::class);

        $invoice1 = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $invoice2 = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $nsfp1 = $service->allocate($invoice1);
        $nsfp2 = $service->allocate($invoice2);

        expect($nsfp1)->toBe('010.000-26.00000001');
        expect($nsfp2)->toBe('010.000-26.00000002');
    });

    it('skips allocation when NSFP is disabled', function () {
        config(['accounting.nsfp.enabled' => false]);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $service = app(NsfpService::class);
        $result = $service->allocate($invoice);

        expect($result)->toBe('');
        expect($invoice->fresh()->nsfp_number)->toBeNull();
    });

    it('skips allocation if invoice already has NSFP', function () {
        config(['accounting.nsfp.enabled' => true]);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create([
                'nsfp_number' => '010.000-26.00000099',
                'invoice_date' => now()->setYear(2026),
            ]);

        $service = app(NsfpService::class);
        $result = $service->allocate($invoice);

        expect($result)->toBe('010.000-26.00000099');
    });

    it('throws exception when no active range exists', function () {
        config(['accounting.nsfp.enabled' => true]);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $service = app(NsfpService::class);
        $service->allocate($invoice);
    })->throws(BusinessRuleException::class, 'Tidak ada range NSFP aktif');

    it('throws exception when range is exhausted', function () {
        config(['accounting.nsfp.enabled' => true]);

        NsfpRange::factory()
            ->withRange(1, 1)
            ->forYear('26')
            ->create(['next_number' => 2, 'used_count' => 1]); // Exhausted

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $service = app(NsfpService::class);
        $service->allocate($invoice);
    })->throws(BusinessRuleException::class, 'sudah habis');

    it('resolves year code from invoice date not current date', function () {
        config(['accounting.nsfp.enabled' => true]);

        // Create range for year 25 (previous year)
        NsfpRange::factory()->withRange(1, 1000)->forYear('25')->create();

        // Invoice dated in 2025 (previous year)
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => '2025-12-15']);

        $service = app(NsfpService::class);
        $nsfpNumber = $service->allocate($invoice);

        expect($nsfpNumber)->toBe('010.000-25.00000001');
    });

    it('dispatches NsfpAllocated event', function () {
        config(['accounting.nsfp.enabled' => true]);

        $eventDispatcher = new RecordingEventDispatcher;
        $this->app->instance(\App\Contracts\Events\EventDispatcherInterface::class, $eventDispatcher);

        NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $service = app(NsfpService::class);
        $service->allocate($invoice);

        expect($eventDispatcher->dispatchCount(NsfpAllocated::class))->toBe(1);
    });

    it('dispatches NsfpLowStock event when below threshold', function () {
        config(['accounting.nsfp.enabled' => true]);
        config(['accounting.nsfp.low_stock_threshold' => 5]);

        $eventDispatcher = new RecordingEventDispatcher;
        $this->app->instance(\App\Contracts\Events\EventDispatcherInterface::class, $eventDispatcher);

        // Range with only 5 numbers remaining (at threshold)
        NsfpRange::factory()
            ->withRange(1, 100)
            ->forYear('26')
            ->create(['next_number' => 96, 'used_count' => 95]);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $service = app(NsfpService::class);
        $service->allocate($invoice);

        expect($eventDispatcher->dispatchCount(NsfpLowStock::class))->toBe(1);
    });

    it('increments range counters correctly', function () {
        config(['accounting.nsfp.enabled' => true]);

        $range = NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create(['invoice_date' => now()->setYear(2026)]);

        $service = app(NsfpService::class);
        $service->allocate($invoice);

        $range->refresh();
        expect($range->next_number)->toBe(2);
        expect($range->used_count)->toBe(1);
    });
});

describe('NsfpService isEnabled', function () {
    it('returns true when enabled in config', function () {
        config(['accounting.nsfp.enabled' => true]);

        $service = app(NsfpService::class);
        expect($service->isEnabled())->toBeTrue();
    });

    it('returns false when disabled in config', function () {
        config(['accounting.nsfp.enabled' => false]);

        $service = app(NsfpService::class);
        expect($service->isEnabled())->toBeFalse();
    });
});
