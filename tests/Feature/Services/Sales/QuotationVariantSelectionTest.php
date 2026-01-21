<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\Sales\QuotationVariantOption;
use App\Models\User;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->quotationService = app(QuotationService::class);
});

describe('QuotationStateMachine::canConvert with variant selection', function () {
    it('allows conversion for single quotation without variant', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::Single->value,
            ]);

        expect($quotation->stateMachine()->canConvert())->toBeTrue();
    });

    it('blocks conversion for multi-option quotation without variant selection', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
                'selected_variant_id' => null,
            ]);

        // Add variant options
        QuotationVariantOption::factory()->count(2)->create([
            'quotation_id' => $quotation->id,
        ]);

        expect($quotation->fresh()->stateMachine()->canConvert())->toBeFalse();
    });

    it('allows conversion for multi-option quotation with variant selected', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
            ]);

        $variant = QuotationVariantOption::factory()->create([
            'quotation_id' => $quotation->id,
        ]);

        $quotation->update(['selected_variant_id' => $variant->bom_id]);

        expect($quotation->fresh()->stateMachine()->canConvert())->toBeTrue();
    });

    it('still blocks conversion for non-approved quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create([
                'quotation_type' => QuotationType::Single->value,
            ]);

        expect($quotation->stateMachine()->canConvert())->toBeFalse();
    });

    it('still blocks conversion for already converted quotation', function () {
        $invoice = Invoice::factory()->create();

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'quotation_type' => QuotationType::Single->value,
            'converted_to_invoice_id' => $invoice->id,
        ]);

        expect($quotation->stateMachine()->canConvert())->toBeFalse();
    });
});

describe('QuotationStateMachine::getConversionBlockReason', function () {
    it('returns variant selection message for multi-option without selection', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
                'selected_variant_id' => null,
            ]);

        $reason = $quotation->stateMachine()->getConversionBlockReason();

        expect($reason)->not->toBeNull()
            ->and($reason)->toContain('varian');
    });

    it('returns approval message for non-approved quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $reason = $quotation->stateMachine()->getConversionBlockReason();

        expect($reason)->not->toBeNull()
            ->and($reason)->toContain('disetujui');
    });

    it('returns approval message for converted quotation (not approved status)', function () {
        // A Converted quotation has status=Converted, which fails the first check (must be Approved)
        $invoice = Invoice::factory()->create();

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'converted_to_invoice_id' => $invoice->id,
        ]);

        $reason = $quotation->stateMachine()->getConversionBlockReason();

        // Converted status != Approved, so it hits "must be approved" first
        expect($reason)->not->toBeNull()
            ->and($reason)->toContain('disetujui');
    });

    it('returns null when conversion is allowed', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::Single->value,
            ]);

        expect($quotation->stateMachine()->getConversionBlockReason())->toBeNull();
    });
});

describe('QuotationService::convertToInvoice with variant guard', function () {
    it('converts single quotation successfully', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::Single->value,
            ]);

        $invoice = $this->quotationService->convertToInvoice($quotation);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
    });

    it('throws exception for multi-option without variant selection', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
                'selected_variant_id' => null,
            ]);

        QuotationVariantOption::factory()->count(2)->create([
            'quotation_id' => $quotation->id,
        ]);

        $this->quotationService->convertToInvoice($quotation->fresh());
    })->throws(InvalidArgumentException::class, 'varian');

    it('converts multi-option quotation with variant selected', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
            ]);

        $variant = QuotationVariantOption::factory()->create([
            'quotation_id' => $quotation->id,
        ]);

        $quotation->update(['selected_variant_id' => $variant->bom_id]);

        $invoice = $this->quotationService->convertToInvoice($quotation->fresh());

        expect($invoice)->toBeInstanceOf(Invoice::class);
    });
});

describe('API endpoint with variant guard', function () {
    it('returns 422 with available variants when multi-option without selection', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
                'selected_variant_id' => null,
            ]);

        QuotationVariantOption::factory()->create([
            'quotation_id' => $quotation->id,
            'display_name' => 'Basic Package',
            'is_recommended' => false,
        ]);

        QuotationVariantOption::factory()->create([
            'quotation_id' => $quotation->id,
            'display_name' => 'Premium Package',
            'is_recommended' => true,
        ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VARIANT_NOT_SELECTED')
            ->assertJsonCount(2, 'available_variants')
            ->assertJsonStructure([
                'message',
                'error_code',
                'available_variants' => [
                    '*' => ['id', 'display_name', 'selling_price', 'is_recommended'],
                ],
                'suggestion',
            ]);
    });

    it('converts single quotation via API', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::Single->value,
            ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'invoice',
                'quotation',
            ]);

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
    });

    it('converts multi-option quotation with variant selected via API', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'quotation_type' => QuotationType::MultiOption->value,
            ]);

        $variant = QuotationVariantOption::factory()->create([
            'quotation_id' => $quotation->id,
        ]);

        $quotation->update(['selected_variant_id' => $variant->bom_id]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertCreated();
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
    });
});

describe('Model helper methods', function () {
    it('isMultiOption returns true for multi-option type', function () {
        $quotation = Quotation::factory()->create([
            'quotation_type' => QuotationType::MultiOption->value,
        ]);

        expect($quotation->isMultiOption())->toBeTrue();
    });

    it('isMultiOption returns false for single type', function () {
        $quotation = Quotation::factory()->create([
            'quotation_type' => QuotationType::Single->value,
        ]);

        expect($quotation->isMultiOption())->toBeFalse();
    });

    it('isMultiOption returns false when quotation_type is single (default)', function () {
        // Default quotation_type is 'single', not null
        $quotation = Quotation::factory()->create();

        expect($quotation->isMultiOption())->toBeFalse();
    });

    it('hasSelectedVariant returns true when variant is selected', function () {
        // Create a BOM first to satisfy foreign key
        $bom = \App\Models\Manufacturing\Bom::factory()->create();

        $quotation = Quotation::factory()->create([
            'selected_variant_id' => $bom->id,
        ]);

        expect($quotation->hasSelectedVariant())->toBeTrue();
    });

    it('hasSelectedVariant returns false when no variant selected', function () {
        $quotation = Quotation::factory()->create([
            'selected_variant_id' => null,
        ]);

        expect($quotation->hasSelectedVariant())->toBeFalse();
    });
});
