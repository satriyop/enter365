<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);

    $this->service = app(DeliveryOrderServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates delivery order with items', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'warehouse_id' => $this->warehouse->id,
            'do_date' => now()->toDateString(),
            'shipping_address' => 'Jl. Test 123',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Test Product',
                    'quantity' => 5,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $do = $this->service->create($data);

        expect($do)
            ->toBeInstanceOf(DeliveryOrder::class)
            ->status->toBe(DocumentStatus::Draft)
            ->contact_id->toBe($this->contact->id)
            ->warehouse_id->toBe($this->warehouse->id)
            ->items->toHaveCount(1);

        expect($do->items->first())
            ->product_id->toBe($this->product->id)
            ->description->toBe('Test Product');
    });

    test('creates delivery order from invoice', function () {
        $invoice = Invoice::factory()
            ->for($this->contact)
            ->create();

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'description' => 'Invoice Item',
            'quantity' => 10,
            'unit_price' => 100000,
            'line_total' => 1000000,
        ]);

        $do = $this->service->createFromInvoice($invoice, [
            'warehouse_id' => $this->warehouse->id,
            'shipping_address' => 'Custom Address',
        ]);

        expect($do)
            ->invoice_id->toBe($invoice->id)
            ->contact_id->toBe($invoice->contact_id)
            ->shipping_address->toBe('Custom Address')
            ->items->toHaveCount(1);

        expect((float) $do->items->first()->quantity)->toBe(10.0);
    });

    test('updates draft delivery order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'shipping_address' => 'Old Address',
            ]);

        $updated = $this->service->update($do, [
            'shipping_address' => 'New Address',
            'driver_name' => 'John Doe',
        ]);

        expect($updated)
            ->shipping_address->toBe('New Address')
            ->driver_name->toBe('John Doe');
    });

    test('throws exception when updating non-draft delivery order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Confirmed]);

        $this->service->update($do, ['shipping_address' => 'Test']);
    })->throws(DocumentLockedException::class);

    test('deletes draft delivery order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $result = $this->service->delete($do);

        expect($result)->toBeTrue()
            ->and(DeliveryOrder::find($do->id))->toBeNull();
    });

    test('throws exception when deleting non-draft delivery order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Confirmed]);

        $this->service->delete($do);
    })->throws(DocumentLockedException::class);
});

describe('State Transitions', function () {
    test('confirms draft delivery order with items', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $confirmed = $this->service->confirm($do, $this->user->id);

        expect($confirmed)
            ->status->toBe(DocumentStatus::Confirmed)
            ->confirmed_at->not->toBeNull();
    });

    test('throws exception when confirming without items', function () {
        $do = DeliveryOrder::factory()->create(['status' => DocumentStatus::Draft]);

        $this->service->confirm($do, $this->user->id);
    })->throws(StateTransitionException::class);

    test('ships confirmed delivery order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Confirmed,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $shipped = $this->service->ship($do, [
            'tracking_number' => 'TRK-123456',
            'driver_name' => 'John Driver',
            'vehicle_number' => 'B 1234 XYZ',
        ], $this->user->id);

        expect($shipped)
            ->status->toBe(DocumentStatus::Shipped)
            ->tracking_number->toBe('TRK-123456')
            ->driver_name->toBe('John Driver')
            ->vehicle_number->toBe('B 1234 XYZ');
    });

    test('deducts inventory when shipping with warehouse', function () {
        // Create inventory service mock before service resolution
        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockOut')
            ->once()
            ->withArgs(function ($product, $warehouse, $quantity, $reference, $sourceType, $sourceId) {
                return $product instanceof \App\Models\Inventory\Product
                    && $warehouse instanceof \App\Models\Inventory\Warehouse
                    && is_int($quantity)
                    && is_string($reference)
                    && $sourceType === DeliveryOrder::class;
            });

        // Recreate service with mocked dependency
        $service = app(DeliveryOrderServiceInterface::class);

        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->state(['product_id' => $this->product->id]), 'items')
            ->create([
                'status' => DocumentStatus::Confirmed,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $service->ship($do, [], $this->user->id);
    });

    test('delivers shipped delivery order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Shipped]);

        $delivered = $this->service->deliver($do, [
            'received_by' => 'Customer Name',
            'delivery_notes' => 'All items received',
        ], $this->user->id);

        expect($delivered)
            ->status->toBe(DocumentStatus::Delivered)
            ->received_by->toBe('Customer Name')
            ->delivery_notes->toBe('All items received');

        // Check all items marked as delivered
        $delivered->items->each(fn ($item) => expect((float) $item->quantity_delivered)->toBe((float) $item->quantity));
    });

    test('cancels delivery order in allowed states', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $cancelled = $this->service->cancel($do, 'Customer cancelled order', $this->user->id);

        expect($cancelled)->status->toBe(DocumentStatus::Cancelled);
    });

    test('throws exception when canceling already delivered order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Delivered]);

        $this->service->cancel($do, 'Test', $this->user->id);
    })->throws(StateTransitionException::class);
});

describe('Partial Delivery', function () {
    test('updates delivery progress for shipped order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->state(['quantity' => 10]), 'items')
            ->create(['status' => DocumentStatus::Shipped]);

        $item = $do->items->first();

        $updated = $this->service->updateDeliveryProgress($do, [
            ['item_id' => $item->id, 'quantity_delivered' => 5],
        ]);

        $updated->refresh();
        expect((float) $updated->items->first()->quantity_delivered)->toBe(5.0);
    });

    test('throws exception when delivered quantity exceeds ordered', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->state(['quantity' => 10]), 'items')
            ->create(['status' => DocumentStatus::Shipped]);

        $item = $do->items->first();

        $this->service->updateDeliveryProgress($do, [
            ['item_id' => $item->id, 'quantity_delivered' => 15],
        ]);
    })->throws(\App\Exceptions\Domain\BusinessRuleException::class);

    test('auto-transitions to delivered when all items delivered', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->state(['quantity' => 10]), 'items')
            ->create(['status' => DocumentStatus::Shipped]);

        $item = $do->items->first();

        $updated = $this->service->updateDeliveryProgress($do, [
            ['item_id' => $item->id, 'quantity_delivered' => 10],
        ]);

        expect($updated->status)->toBe(DocumentStatus::Delivered);
    });

    test('throws exception when updating progress for non-shipped order', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $item = $do->items->first();

        $this->service->updateDeliveryProgress($do, [
            ['item_id' => $item->id, 'quantity_delivered' => 5],
        ]);
    })->throws(StateTransitionException::class);
});

describe('Duplication', function () {
    test('duplicates delivery order as new draft', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->count(2), 'items')
            ->create([
                'status' => DocumentStatus::Delivered,
                'tracking_number' => 'OLD-TRACK',
            ]);

        $duplicate = $this->service->duplicate($do);

        expect($duplicate)
            ->status->toBe(DocumentStatus::Draft)
            ->do_number->not->toBe($do->do_number)
            ->tracking_number->toBeNull()
            ->items->toHaveCount(2);

        // Items should be reset
        $duplicate->items->each(fn ($item) => expect((float) $item->quantity_delivered)->toBe(0.0));
    });
});

describe('Query Methods', function () {
    test('gets delivery orders for invoice', function () {
        $invoice = Invoice::factory()->create();

        DeliveryOrder::factory()->count(3)->create(['invoice_id' => $invoice->id]);
        DeliveryOrder::factory()->create(); // Different invoice

        $results = $this->service->getForInvoice($invoice);

        expect($results)->toHaveCount(3);
    });
});

describe('Reverse Shipment', function () {
    test('reverses shipment and restores inventory', function () {
        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockIn')
            ->once()
            ->withArgs(function ($product, $warehouse, $quantity, $unitCost, $notes, $sourceType, $sourceId) {
                return $product instanceof Product
                    && $warehouse instanceof Warehouse
                    && is_int($quantity)
                    && is_int($unitCost)
                    && $sourceType === DeliveryOrder::class;
            });

        $journalService = $this->mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->never();

        $service = app(DeliveryOrderServiceInterface::class);

        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->state(['product_id' => $this->product->id]), 'items')
            ->shipped()
            ->create(['warehouse_id' => $this->warehouse->id]);

        $result = $service->reverseShipment($do, 'Faktur dibatalkan');

        expect($result->status)->toBe(DocumentStatus::Cancelled);
        expect($result->cancellation_reason)->toBe('Faktur dibatalkan');
    });

    test('reverses COGS JE during shipment reversal', function () {
        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockIn')->once();

        $cogsJe = JournalEntry::factory()->create([
            'source_type' => DeliveryOrder::class,
            'is_reversed' => false,
            'is_posted' => true,
        ]);

        $journalService = $this->mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')
            ->once()
            ->withArgs(function ($je, $description) use ($cogsJe) {
                return $je->id === $cogsJe->id
                    && str_contains($description, 'Pembatalan pengiriman');
            });

        $service = app(DeliveryOrderServiceInterface::class);

        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory()->state(['product_id' => $this->product->id]), 'items')
            ->shipped()
            ->create(['warehouse_id' => $this->warehouse->id]);

        // Link the COGS JE to this DO
        $cogsJe->update(['source_id' => $do->id]);

        $service->reverseShipment($do, 'Test COGS reversal');

        expect($do->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    test('cannot reverse shipment of delivered DO', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->delivered()
            ->create();

        $this->service->reverseShipment($do, 'Should fail');
    })->throws(StateTransitionException::class);

    test('cannot reverse shipment of draft DO', function () {
        $do = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $this->service->reverseShipment($do, 'Should fail');
    })->throws(StateTransitionException::class);
});
