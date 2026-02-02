<?php

declare(strict_types=1);

use App\Contracts\Sales\QuotationConversionServiceInterface;
use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\Bom;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('QuotationConversionService', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->contact = Contact::factory()->create();
        $this->service = app(QuotationConversionServiceInterface::class);
        $this->actingAs($this->user);
    });

    describe('convertToInvoice', function () {
        it('converts approved quotation to invoice successfully', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'subject' => 'Test Quotation',
                    'subtotal' => 1000000,
                    'tax_amount' => 100000,
                    'tax_rate' => 10,
                    'discount_amount' => 50000,
                    'total_amount' => 1050000,
                    'currency' => 'IDR',
                    'exchange_rate' => 1,
                    'base_currency_total' => 1050000,
                ]);

            QuotationItem::factory()
                ->forQuotation($quotation)
                ->create([
                    'quantity' => 10,
                    'unit_price' => 100000,
                    'line_total' => 1000000,
                ]);

            $invoice = $this->service->convertToInvoice($quotation);

            expect($invoice)->toBeInstanceOf(Invoice::class)
                ->and($invoice->contact_id)->toBe($this->contact->id)
                ->and($invoice->description)->toBe('Test Quotation')
                ->and($invoice->reference)->toBe($quotation->getFullNumber())
                ->and((float) $invoice->subtotal)->toBe(1000000.0)
                ->and((float) $invoice->tax_amount)->toBe(100000.0)
                ->and((float) $invoice->tax_rate)->toBe(10.0)
                ->and((float) $invoice->discount_amount)->toBe(50000.0)
                ->and((float) $invoice->total_amount)->toBe(1050000.0)
                ->and($invoice->currency)->toBe('IDR')
                ->and((float) $invoice->exchange_rate)->toBe(1.0)
                ->and((float) $invoice->base_currency_total)->toBe(1050000.0)
                ->and((float) $invoice->paid_amount)->toBe(0.0)
                ->and($invoice->status)->toBe(DocumentStatus::Draft)
                ->and($invoice->items)->toHaveCount(1)
                ->and($invoice->relationLoaded('items'))->toBeTrue()
                ->and($invoice->relationLoaded('contact'))->toBeTrue();
        });

        it('copies all quotation items to invoice items', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $item1 = QuotationItem::factory()
                ->forQuotation($quotation)
                ->create([
                    'description' => 'Item 1',
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                    'line_total' => 500000,
                ]);

            $item2 = QuotationItem::factory()
                ->forQuotation($quotation)
                ->create([
                    'description' => 'Item 2',
                    'quantity' => 3,
                    'unit' => 'box',
                    'unit_price' => 200000,
                    'line_total' => 600000,
                ]);

            $invoice = $this->service->convertToInvoice($quotation);

            expect($invoice->items)->toHaveCount(2);

            $invoiceItem1 = $invoice->items->first();
            expect($invoiceItem1->product_id)->toBe($item1->product_id)
                ->and($invoiceItem1->description)->toBe('Item 1')
                ->and((float) $invoiceItem1->quantity)->toBe(5.0)
                ->and($invoiceItem1->unit)->toBe('pcs')
                ->and((float) $invoiceItem1->unit_price)->toBe(100000.0)
                ->and((float) $invoiceItem1->line_total)->toBe(500000.0);

            $invoiceItem2 = $invoice->items->last();
            expect($invoiceItem2->product_id)->toBe($item2->product_id)
                ->and($invoiceItem2->description)->toBe('Item 2')
                ->and((float) $invoiceItem2->quantity)->toBe(3.0)
                ->and($invoiceItem2->unit)->toBe('box')
                ->and((float) $invoiceItem2->unit_price)->toBe(200000.0)
                ->and((float) $invoiceItem2->line_total)->toBe(600000.0);
        });

        it('clears next_follow_up_at after conversion', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'next_follow_up_at' => now()->addDays(7),
                ]);

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->convertToInvoice($quotation);

            $quotation->refresh();
            expect($quotation->next_follow_up_at)->toBeNull();
        });

        it('sets outcome to Won if not already set', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'outcome' => null,
                ]);

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->convertToInvoice($quotation);

            $quotation->refresh();
            expect($quotation->outcome)->toBe(QuotationOutcome::Won)
                ->and($quotation->won_reason)->toBe('converted_to_invoice')
                ->and($quotation->outcome_at)->not->toBeNull();
        });

        it('preserves existing outcome if already set', function () {
            $outcomeAt = now()->subDay();
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'outcome' => QuotationOutcome::Won,
                    'won_reason' => 'customer_confirmed',
                    'outcome_at' => $outcomeAt,
                ]);

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->convertToInvoice($quotation);

            $quotation->refresh();
            expect($quotation->outcome)->toBe(QuotationOutcome::Won)
                ->and($quotation->won_reason)->toBe('customer_confirmed')
                ->and($quotation->outcome_at->toDateTimeString())->toBe($outcomeAt->toDateTimeString());
        });

        it('updates quotation with conversion data', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $invoice = $this->service->convertToInvoice($quotation);

            $quotation->refresh();
            expect($quotation->converted_to_invoice_id)->toBe($invoice->id)
                ->and($quotation->converted_at)->not->toBeNull()
                ->and($quotation->status)->toBe(DocumentStatus::Converted);
        });

        it('throws exception for draft quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Draft]);

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->convertToInvoice($quotation);
        })->throws(BusinessRuleException::class);

        it('throws exception for submitted quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Submitted]);

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->convertToInvoice($quotation);
        })->throws(BusinessRuleException::class);

        it('throws exception for already converted quotation', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            // First conversion should succeed
            $this->service->convertToInvoice($quotation);

            $quotation->refresh();

            // Second conversion should fail
            $this->service->convertToInvoice($quotation);
        })->throws(BusinessRuleException::class);

        it('throws BusinessRuleException with VARIANT_NOT_SELECTED for multi-option without selection', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->multiOption()
                ->forContact($this->contact)
                ->create();

            // Create BOMs for variants
            $bom1 = Bom::factory()->create();
            $bom2 = Bom::factory()->create();

            // Create variant options but don't select any
            $quotation->variantOptions()->createMany([
                [
                    'bom_id' => $bom1->id,
                    'display_name' => 'Option A',
                    'selling_price' => 1000000,
                    'is_recommended' => true,
                    'description' => 'First option',
                ],
                [
                    'bom_id' => $bom2->id,
                    'display_name' => 'Option B',
                    'selling_price' => 1500000,
                    'is_recommended' => false,
                    'description' => 'Second option',
                ],
            ]);

            try {
                $this->service->convertToInvoice($quotation);
                throw new \Exception('Expected BusinessRuleException was not thrown');
            } catch (BusinessRuleException $e) {
                expect($e->getContext())->toHaveKey('error_code')
                    ->and($e->getContext()['error_code'])->toBe('VARIANT_NOT_SELECTED')
                    ->and($e->getContext())->toHaveKey('available_variants')
                    ->and($e->getContext()['available_variants'])->toHaveCount(2)
                    ->and($e->getContext())->toHaveKey('suggestion')
                    ->and($e->getContext()['suggestion'])->toContain('Pilih salah satu varian');
            }
        });

        it('converts multi-option quotation with selected variant', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->multiOption()
                ->forContact($this->contact)
                ->create();

            // Create BOM for variant
            $bom = Bom::factory()->create();

            // Create and select a variant
            $selectedVariant = $quotation->variantOptions()->create([
                'bom_id' => $bom->id,
                'display_name' => 'Selected Option',
                'selling_price' => 1000000,
                'is_recommended' => true,
                'description' => 'The selected option',
            ]);

            $quotation->update(['selected_variant_id' => $selectedVariant->id]);

            // Add at least one item (multi-option quotations still need items)
            QuotationItem::factory()->forQuotation($quotation)->create();

            $invoice = $this->service->convertToInvoice($quotation);

            expect($invoice)->toBeInstanceOf(Invoice::class)
                ->and($quotation->refresh()->status)->toBe(DocumentStatus::Converted);
        });
    });

    describe('canConvertToInvoice', function () {
        it('returns true for approved quotation', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->canConvertToInvoice($quotation);

            expect($result)->toBeTrue();
        });

        it('returns false for draft quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Draft]);

            $result = $this->service->canConvertToInvoice($quotation);

            expect($result)->toBeFalse();
        });

        it('returns false for submitted quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Submitted]);

            $result = $this->service->canConvertToInvoice($quotation);

            expect($result)->toBeFalse();
        });

        it('returns false for already converted quotation', function () {
            $invoice = Invoice::factory()->create();
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'status' => DocumentStatus::Converted,
                    'converted_to_invoice_id' => $invoice->id,
                    'converted_at' => now(),
                ]);

            $result = $this->service->canConvertToInvoice($quotation);

            expect($result)->toBeFalse();
        });

        it('returns false for cancelled quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Cancelled]);

            $result = $this->service->canConvertToInvoice($quotation);

            expect($result)->toBeFalse();
        });
    });

    describe('getConversionStatus', function () {
        it('returns converted status for converted quotation', function () {
            $invoice = Invoice::factory()->create();
            $convertedAt = now()->subHour();
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'status' => DocumentStatus::Converted,
                    'converted_to_invoice_id' => $invoice->id,
                    'converted_at' => $convertedAt,
                ]);

            $status = $this->service->getConversionStatus($quotation);

            expect($status)->toHaveKey('can_convert')
                ->and($status['can_convert'])->toBeFalse()
                ->and($status)->toHaveKey('converted')
                ->and($status['converted'])->toBeTrue()
                ->and($status)->toHaveKey('converted_to')
                ->and($status['converted_to']['type'])->toBe('invoice')
                ->and($status['converted_to']['id'])->toBe($invoice->id)
                ->and($status['converted_to']['converted_at']->toDateTimeString())->toBe($convertedAt->toDateTimeString())
                ->and($status)->toHaveKey('reason')
                ->and($status['reason'])->toBe('Sudah dikonversi ke invoice.');
        });

        it('returns cannot convert status for draft quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Draft]);

            $status = $this->service->getConversionStatus($quotation);

            expect($status)->toHaveKey('can_convert')
                ->and($status['can_convert'])->toBeFalse()
                ->and($status)->toHaveKey('converted')
                ->and($status['converted'])->toBeFalse()
                ->and($status)->toHaveKey('converted_to')
                ->and($status['converted_to'])->toBeNull()
                ->and($status)->toHaveKey('reason')
                ->and($status['reason'])->toBe('Penawaran harus disetujui terlebih dahulu.');
        });

        it('returns cannot convert status for submitted quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Submitted]);

            $status = $this->service->getConversionStatus($quotation);

            expect($status['can_convert'])->toBeFalse()
                ->and($status['converted'])->toBeFalse()
                ->and($status['converted_to'])->toBeNull()
                ->and($status['reason'])->toBe('Penawaran harus disetujui terlebih dahulu.');
        });

        it('returns can convert status for approved quotation', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $status = $this->service->getConversionStatus($quotation);

            expect($status)->toHaveKey('can_convert')
                ->and($status['can_convert'])->toBeTrue()
                ->and($status)->toHaveKey('converted')
                ->and($status['converted'])->toBeFalse()
                ->and($status)->toHaveKey('converted_to')
                ->and($status['converted_to'])->toBeNull()
                ->and($status)->toHaveKey('reason')
                ->and($status['reason'])->toBeNull();
        });

        it('returns cannot convert status for cancelled quotation', function () {
            $quotation = Quotation::factory()
                ->forContact($this->contact)
                ->create(['status' => DocumentStatus::Cancelled]);

            $status = $this->service->getConversionStatus($quotation);

            expect($status['can_convert'])->toBeFalse()
                ->and($status['converted'])->toBeFalse()
                ->and($status['converted_to'])->toBeNull()
                ->and($status['reason'])->toBe('Penawaran harus disetujui terlebih dahulu.');
        });
    });
});
