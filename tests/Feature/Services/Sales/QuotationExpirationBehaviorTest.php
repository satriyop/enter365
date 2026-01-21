<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
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

describe('markExpired behavior', function () {
    it('expires draft quotations past valid_until', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
            'valid_until' => now()->subDay(),
        ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
    });

    it('expires submitted quotations past valid_until', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create([
                'valid_until' => now()->subDay(),
            ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
    });

    it('expires unsent approved quotations', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'valid_until' => now()->subDay(),
                'sent_at' => null, // Not sent
            ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
    });

    it('does not expire sent approved quotations', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'valid_until' => now()->subDay(),
                'sent_at' => now()->subWeek(), // Was sent to customer
                'sent_by' => $this->user->id,
            ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Approved); // Unchanged
    });

    it('does not expire quotations with future valid_until', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
            'valid_until' => now()->addWeek(),
        ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Draft);
    });

    it('does not expire converted quotations', function () {
        $invoice = \App\Models\Sales\Invoice::factory()->create();

        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Converted,
            'valid_until' => now()->subDay(),
            'converted_to_invoice_id' => $invoice->id,
        ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
    });

    it('does not expire cancelled quotations', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Cancelled,
            'valid_until' => now()->subDay(),
        ]);

        $this->quotationService->markExpired();

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('returns count of expired quotations', function () {
        Quotation::factory()->count(3)->create([
            'status' => DocumentStatus::Draft,
            'valid_until' => now()->subDay(),
        ]);

        $count = $this->quotationService->markExpired();

        expect($count)->toBe(3);
    });
});

describe('markAsSent behavior', function () {
    it('can mark approved quotation as sent', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $result = $this->quotationService->markAsSent(
            $quotation,
            'customer@example.com',
            'email'
        );

        expect($result->sent_at)->not->toBeNull();
        expect($result->sent_by)->toBe($this->user->id);
        expect($result->sent_to_email)->toBe('customer@example.com');
        expect($result->sent_via)->toBe('email');
    });

    it('defaults to contact email if none provided', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $result = $this->quotationService->markAsSent($quotation);

        expect($result->sent_to_email)->toBe($quotation->contact->email);
    });

    it('cannot mark non-approved quotation as sent', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $this->quotationService->markAsSent($quotation);
    })->throws(InvalidArgumentException::class, 'disetujui');

    it('cannot mark already sent quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'sent_at' => now()->subDay(),
                'sent_by' => $this->user->id,
            ]);

        $this->quotationService->markAsSent($quotation);
    })->throws(InvalidArgumentException::class, 'sudah ditandai');

    it('isSent helper returns correct value', function () {
        $unsent = Quotation::factory()->create(['sent_at' => null]);
        $sent = Quotation::factory()->create(['sent_at' => now()]);

        expect($unsent->isSent())->toBeFalse();
        expect($sent->isSent())->toBeTrue();
    });
});

describe('API endpoint for mark-sent', function () {
    it('can mark quotation as sent via API', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-sent", [
            'email' => 'test@example.com',
            'via' => 'email',
        ]);

        $response->assertOk();
        expect($quotation->fresh())
            ->sent_at->not->toBeNull()
            ->sent_to_email->toBe('test@example.com')
            ->sent_via->toBe('email');
    });

    it('validates email format', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-sent", [
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates via options', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-sent", [
            'via' => 'invalid-option',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['via']);
    });

    it('returns 422 when cannot mark as sent', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-sent");

        $response->assertUnprocessable();
    });
});

describe('Integration: sent quotation does not expire', function () {
    it('full flow: mark sent then try expire', function () {
        // Create approved quotation with past valid_until
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create([
                'valid_until' => now()->subDay(),
            ]);

        // Mark as sent via API
        $this->postJson("/api/v1/quotations/{$quotation->id}/mark-sent", [
            'email' => 'customer@example.com',
        ])->assertOk();

        // Try to expire
        $this->quotationService->markExpired();

        // Should still be Approved (not Expired)
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Approved);
        expect($quotation->fresh()->isSent())->toBeTrue();
    });
});
