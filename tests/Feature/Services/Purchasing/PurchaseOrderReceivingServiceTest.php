<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(PurchaseOrderReceivingService::class);
});

describe('canReceive', function () {
    it('returns true for approved purchase order', function () {
        $po = PurchaseOrder::factory()->approved()->create();

        expect($this->service->canReceive($po))->toBeTrue();
    });

    it('returns true for partial purchase order', function () {
        $po = PurchaseOrder::factory()->partial()->create();

        expect($this->service->canReceive($po))->toBeTrue();
    });

    it('returns false for draft purchase order', function () {
        $po = PurchaseOrder::factory()->draft()->create();

        expect($this->service->canReceive($po))->toBeFalse();
    });

    it('returns false for cancelled purchase order', function () {
        $po = PurchaseOrder::factory()->cancelled()->create();

        expect($this->service->canReceive($po))->toBeFalse();
    });

    it('returns false for received purchase order', function () {
        $po = PurchaseOrder::factory()->received()->create();

        expect($this->service->canReceive($po))->toBeFalse();
    });
});

describe('updateReceivingStatus', function () {
    it('transitions approved PO to partial when items partially received', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 5]);

        $result = $this->service->updateReceivingStatus($po);

        expect($result->status)->toBe(DocumentStatus::Partial);
    });

    it('transitions to received when all items fully received', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 10]);

        $result = $this->service->updateReceivingStatus($po);

        expect($result->status)->toBe(DocumentStatus::Received);
    });

    it('does not transition draft PO', function () {
        $po = PurchaseOrder::factory()->draft()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 10]);

        $result = $this->service->updateReceivingStatus($po);

        expect($result->status)->toBe(DocumentStatus::Draft);
    });

    it('transitions partial to received when remaining items received', function () {
        $po = PurchaseOrder::factory()->partial()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 10]);

        $result = $this->service->updateReceivingStatus($po);

        expect($result->status)->toBe(DocumentStatus::Received);
    });

    it('stays approved when no items received', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 0]);

        $result = $this->service->updateReceivingStatus($po);

        expect($result->status)->toBe(DocumentStatus::Approved);
    });
});

describe('getReceivingSummary', function () {
    it('returns correct summary for approved PO with no receiving', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 0]);

        $summary = $this->service->getReceivingSummary($po);

        expect($summary)
            ->toHaveKeys(['status', 'progress', 'is_fully_received', 'has_received_items'])
            ->and($summary['progress'])->toBe(0.0)
            ->and($summary['is_fully_received'])->toBeFalse()
            ->and($summary['has_received_items'])->toBeFalse();
    });

    it('returns correct summary for partially received PO', function () {
        $po = PurchaseOrder::factory()->partial()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 5]);

        $summary = $this->service->getReceivingSummary($po);

        expect($summary['progress'])->toBe(50.0)
            ->and($summary['is_fully_received'])->toBeFalse()
            ->and($summary['has_received_items'])->toBeTrue();
    });

    it('returns correct summary for fully received PO', function () {
        $po = PurchaseOrder::factory()->received()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 10]);

        $summary = $this->service->getReceivingSummary($po);

        expect($summary['progress'])->toBe(100.0)
            ->and($summary['is_fully_received'])->toBeTrue()
            ->and($summary['has_received_items'])->toBeTrue();
    });
});

describe('getItemsReceivingStatus', function () {
    it('returns item receiving details', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 3, 'description' => 'Test Item']);

        $items = $this->service->getItemsReceivingStatus($po);

        expect($items)->toHaveCount(1)
            ->and($items[0])
            ->toHaveKeys(['item_id', 'product_name', 'ordered', 'received', 'remaining', 'is_complete'])
            ->and($items[0]['ordered'])->toBe(10.0)
            ->and($items[0]['received'])->toBe(3.0)
            ->and($items[0]['remaining'])->toBe(7.0)
            ->and($items[0]['is_complete'])->toBeFalse();
    });

    it('marks complete items correctly', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 10]);

        $items = $this->service->getItemsReceivingStatus($po);

        expect($items[0]['is_complete'])->toBeTrue()
            ->and($items[0]['remaining'])->toBe(0.0);
    });

    it('handles multiple items', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 5, 'quantity_received' => 5]);
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($po)
            ->create(['quantity' => 10, 'quantity_received' => 0]);

        $items = $this->service->getItemsReceivingStatus($po);

        expect($items)->toHaveCount(2);
        $complete = collect($items)->where('is_complete', true);
        $incomplete = collect($items)->where('is_complete', false);

        expect($complete)->toHaveCount(1)
            ->and($incomplete)->toHaveCount(1);
    });
});
