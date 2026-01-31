<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->quotationService = app(QuotationService::class);
});

describe('Clear follow-up on conversion', function () {
    it('clears follow-up when quotation is converted', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'next_follow_up_at' => now()->addDays(3),
                'assigned_to' => $this->user->id,
            ]);

        $invoice = $this->quotationService->convertToInvoice($quotation);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($quotation->fresh())
            ->next_follow_up_at->toBeNull()
            ->status->toBe(DocumentStatus::Converted);
    });

    it('quotation without follow-up converts normally', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'next_follow_up_at' => null,
            ]);

        $invoice = $this->quotationService->convertToInvoice($quotation);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
    });
});

describe('Auto-mark as Won on conversion', function () {
    it('auto-marks as won when converted without outcome', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'outcome' => null,
            ]);

        $this->quotationService->convertToInvoice($quotation);

        expect($quotation->fresh())
            ->outcome->toBe(QuotationOutcome::Won)
            ->won_reason->toBe('converted_to_invoice')
            ->outcome_at->not->toBeNull();
    });

    it('preserves existing won outcome when converted', function () {
        $outcomeAt = now()->subHour();

        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'outcome' => 'won',
                'won_reason' => 'harga_kompetitif',
                'outcome_at' => $outcomeAt,
            ]);

        $this->quotationService->convertToInvoice($quotation);

        $fresh = $quotation->fresh();
        expect($fresh->outcome)->toBe(QuotationOutcome::Won);
        expect($fresh->won_reason)->toBe('harga_kompetitif');
        // outcome_at should be preserved (within a second tolerance for datetime comparison)
        expect($fresh->outcome_at->diffInSeconds($outcomeAt))->toBeLessThan(2);
    });

    it('preserves existing lost outcome when converted', function () {
        // Edge case: A Lost quotation shouldn't normally be converted,
        // but if business allows it, preserve the outcome
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'outcome' => 'lost',
                'lost_reason' => 'harga_tinggi',
                'outcome_at' => now()->subDay(),
            ]);

        $this->quotationService->convertToInvoice($quotation);

        expect($quotation->fresh())
            ->outcome->toBe(QuotationOutcome::Lost)
            ->lost_reason->toBe('harga_tinggi');
    });
});

describe('API endpoint clears follow-up', function () {
    it('clears follow-up via API conversion', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::Single->value,
                'next_follow_up_at' => now()->addWeek(),
            ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertCreated();
        expect($quotation->fresh())
            ->next_follow_up_at->toBeNull()
            ->status->toBe(DocumentStatus::Converted);
    });

    it('auto-marks as won via API conversion', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::Single->value,
                'outcome' => null,
            ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertCreated();
        expect($quotation->fresh())
            ->outcome->toBe(QuotationOutcome::Won)
            ->won_reason->toBe('converted_to_invoice');
    });
});
