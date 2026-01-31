<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Events\QuotationCancelled;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = authenticatedAdmin();
    $this->quotationService = app(QuotationService::class);
});

describe('QuotationService::cancel', function () {
    it('can cancel draft quotation', function () {
        Event::fake([QuotationCancelled::class]);

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
        ]);

        $result = $this->quotationService->cancel($quotation, 'Customer requested cancellation');

        expect($result->status)->toBe(DocumentStatus::Cancelled);
        expect($result->cancellation_reason)->toBe('Customer requested cancellation');
        expect($result->cancelled_at)->not->toBeNull();
        expect($result->cancelled_by)->toBe($this->user->id);

        Event::assertDispatched(QuotationCancelled::class, function ($event) use ($quotation) {
            return $event->quotationId === $quotation->id
                && $event->previousStatus === DocumentStatus::Draft;
        });
    });

    it('can cancel submitted quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $result = $this->quotationService->cancel($quotation);

        expect($result->status)->toBe(DocumentStatus::Cancelled);
    });

    it('can cancel approved quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $result = $this->quotationService->cancel($quotation, 'Project cancelled');

        expect($result->status)->toBe(DocumentStatus::Cancelled);
        expect($result->cancellation_reason)->toBe('Project cancelled');
    });

    it('cannot cancel converted quotation', function () {
        // Create a real invoice for the foreign key
        $invoice = Invoice::factory()->create();

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'converted_to_invoice_id' => $invoice->id,
        ]);

        $this->quotationService->cancel($quotation);
    })->throws(StateTransitionException::class);

    it('cannot cancel already cancelled quotation', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Cancelled,
        ]);

        $this->quotationService->cancel($quotation);
    })->throws(StateTransitionException::class);

    it('cannot cancel expired quotation', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Expired,
        ]);

        $this->quotationService->cancel($quotation);
    })->throws(StateTransitionException::class);

    it('clears follow-up on cancellation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create([
                'next_follow_up_at' => now()->addDays(3),
            ]);

        $result = $this->quotationService->cancel($quotation);

        expect($result->next_follow_up_at)->toBeNull();
    });

    it('can cancel without reason', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
        ]);

        $result = $this->quotationService->cancel($quotation);

        expect($result->status)->toBe(DocumentStatus::Cancelled);
        expect($result->cancellation_reason)->toBeNull();
    });
});

describe('Quotation revision after cancellation', function () {
    it('can revise cancelled quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Cancelled,
            ]);

        $result = $this->quotationService->revise($quotation);

        expect($result->status)->toBe(DocumentStatus::Draft);
        expect($result->original_quotation_id)->toBe($quotation->id);
    });
});

describe('State machine canCancel', function () {
    it('returns true for draft status', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
        ]);

        expect($quotation->stateMachine()->canCancel())->toBeTrue();
    });

    it('returns true for submitted status', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        expect($quotation->stateMachine()->canCancel())->toBeTrue();
    });

    it('returns true for approved status', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        expect($quotation->stateMachine()->canCancel())->toBeTrue();
    });

    it('returns false for converted status', function () {
        $invoice = Invoice::factory()->create();

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'converted_to_invoice_id' => $invoice->id,
        ]);

        expect($quotation->stateMachine()->canCancel())->toBeFalse();
    });

    it('returns false for cancelled status', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Cancelled,
        ]);

        expect($quotation->stateMachine()->canCancel())->toBeFalse();
    });

    it('returns false for expired status', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Expired,
        ]);

        expect($quotation->stateMachine()->canCancel())->toBeFalse();
    });
});

describe('API endpoint', function () {
    it('can cancel quotation via API', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/cancel", [
            'reason' => 'Project cancelled by customer',
        ]);

        $response->assertOk();
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($quotation->fresh()->cancellation_reason)->toBe('Project cancelled by customer');
    });

    it('can cancel quotation via API without reason', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
        ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/cancel");

        $response->assertOk();
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('returns 422 when cannot cancel', function () {
        $invoice = Invoice::factory()->create();

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'converted_to_invoice_id' => $invoice->id,
        ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/cancel");

        $response->assertUnprocessable();
    });

    it('validates reason max length', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
        ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/cancel", [
            'reason' => str_repeat('a', 1001),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reason']);
    });
});

describe('Active scope excludes cancelled', function () {
    it('excludes cancelled quotations from active scope', function () {
        Quotation::factory()->create(['status' => DocumentStatus::Draft]);
        Quotation::factory()->create(['status' => DocumentStatus::Submitted]);
        Quotation::factory()->create(['status' => DocumentStatus::Cancelled]);

        $invoice = Invoice::factory()->create();
        Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'converted_to_invoice_id' => $invoice->id,
        ]);

        $activeQuotations = Quotation::active()->get();

        expect($activeQuotations)->toHaveCount(2);
        expect($activeQuotations->pluck('status')->toArray())
            ->each->not->toBe(DocumentStatus::Cancelled);
    });
});
