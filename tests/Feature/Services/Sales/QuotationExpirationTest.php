<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(QuotationService::class);
});

describe('markExpired', function () {
    it('marks expired draft quotations through state machine', function () {
        Event::fake();

        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'valid_until' => now()->subDay(),
            ]);

        $count = $this->service->markExpired();

        expect($count)->toBe(1);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);

        Event::assertDispatched(\App\Domain\Sales\Quotations\Events\QuotationExpired::class);
        Event::assertDispatched(\App\Domain\Sales\Quotations\Events\QuotationStatusChanged::class);
    });

    it('marks expired submitted quotations through state machine', function () {
        Event::fake();

        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'valid_until' => now()->subDay(),
            ]);

        $count = $this->service->markExpired();

        expect($count)->toBe(1);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);

        Event::assertDispatched(\App\Domain\Sales\Quotations\Events\QuotationExpired::class);
    });

    it('does not mark quotations with future valid_until', function () {
        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'valid_until' => now()->addDays(7),
            ]);

        $count = $this->service->markExpired();

        expect($count)->toBe(0);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Draft);
    });

    it('marks unsent approved quotations as expired', function () {
        // With Option C (sent_at tracking): unsent approved quotations DO expire
        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Approved,
                'valid_until' => now()->subDay(),
                'sent_at' => null, // Not sent to customer
            ]);

        $count = $this->service->markExpired();

        // Unsent approved quotations expire (customer hasn't seen them)
        expect($count)->toBe(1);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
    });

    it('does not mark sent approved quotations', function () {
        // Sent approved quotations are protected from expiration
        $user = \App\Models\User::factory()->create();
        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Approved,
                'valid_until' => now()->subDay(),
                'sent_at' => now()->subWeek(), // Was sent to customer
                'sent_by' => $user->id,
            ]);

        $count = $this->service->markExpired();

        // Sent approved quotations do NOT expire
        expect($count)->toBe(0);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Approved);
    });

    it('does not mark already expired quotations', function () {
        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Expired,
                'valid_until' => now()->subWeek(),
            ]);

        $count = $this->service->markExpired();

        expect($count)->toBe(0);
        expect($quotation->fresh()->status)->toBe(DocumentStatus::Expired);
    });

    it('processes multiple expired quotations', function () {
        Event::fake();

        Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->count(3)
            ->create([
                'status' => DocumentStatus::Draft,
                'valid_until' => now()->subDay(),
            ]);

        Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->count(2)
            ->create([
                'status' => DocumentStatus::Submitted,
                'valid_until' => now()->subDays(2),
            ]);

        $count = $this->service->markExpired();

        expect($count)->toBe(5);
        expect(Quotation::where('status', DocumentStatus::Expired)->count())->toBe(5);
    });

    it('records status history when marking as expired', function () {
        Event::fake();

        $quotation = Quotation::factory()
            ->has(\App\Models\Sales\QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'valid_until' => now()->subDay(),
            ]);

        $this->service->markExpired();

        $quotation->refresh();

        $history = $quotation->statusHistories()->latest()->first();
        expect($history)->not->toBeNull();
        expect($history->to_status)->toBe(DocumentStatus::Expired);
        expect($history->from_status)->toBe(DocumentStatus::Draft);
    });
});
