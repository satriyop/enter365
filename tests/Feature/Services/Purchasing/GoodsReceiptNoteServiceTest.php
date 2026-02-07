<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Purchasing\GoodsReceiptNoteServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\AccountingPolicy;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->vendor = Contact::factory()->vendor()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);

    $this->service = app(GoodsReceiptNoteServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates goods receipt note', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $data = [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'supplier_do_number' => 'SDO-123',
        ];

        $grn = $this->service->create($data);

        expect($grn)
            ->toBeInstanceOf(GoodsReceiptNote::class)
            ->status->toBe(DocumentStatus::Draft)
            ->purchase_order_id->toBe($po->id)
            ->warehouse_id->toBe($this->warehouse->id)
            ->supplier_do_number->toBe('SDO-123')
            ->grn_number->not->toBeEmpty();
    });

    test('creates GRN from purchase order with remaining items', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'product_id' => $this->product->id,
                'quantity' => 100,
                'quantity_received' => 30,
            ]), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $grn = $this->service->createFromPurchaseOrder($po, [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
        ]);

        expect($grn)
            ->purchase_order_id->toBe($po->id)
            ->items->toHaveCount(1);

        // Should only create item for remaining quantity (70)
        expect((float) $grn->items->first()->quantity_ordered)->toBe(70.0);
    });

    test('throws exception when creating GRN from non-approved PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->createFromPurchaseOrder($po, [
            'warehouse_id' => $this->warehouse->id,
        ]);
    })->throws(StateTransitionException::class);

    test('updates draft GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'supplier_do_number' => 'OLD-123',
            ]);

        $updated = $this->service->update($grn, [
            'supplier_do_number' => 'NEW-456',
            'driver_name' => 'John Driver',
        ]);

        expect($updated)
            ->supplier_do_number->toBe('NEW-456')
            ->driver_name->toBe('John Driver');
    });

    test('throws exception when updating non-draft GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Completed]);

        $this->service->update($grn, ['driver_name' => 'Test']);
    })->throws(DocumentLockedException::class);

    test('deletes draft GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->delete($grn);

        expect(GoodsReceiptNote::find($grn->id))->toBeNull();
    });

    test('throws exception when deleting non-draft GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Receiving]);

        $this->service->delete($grn);
    })->throws(DocumentLockedException::class);
});

describe('Item Management', function () {
    test('updates GRN item quantities', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state([
                'quantity_ordered' => 100,
                'quantity_received' => 0,
                'quantity_rejected' => 0,
            ]), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $item = $grn->items->first();

        $updated = $this->service->updateItem($item, [
            'quantity_received' => 90,
            'quantity_rejected' => 5,
            'rejection_reason' => 'Damaged packaging',
        ]);

        expect($updated)
            ->quantity_received->toBe(90)
            ->quantity_rejected->toBe(5)
            ->rejection_reason->toBe('Damaged packaging');
    });

    test('throws exception when received quantity exceeds ordered', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state([
                'quantity_ordered' => 100,
            ]), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $item = $grn->items->first();

        $this->service->updateItem($item, [
            'quantity_received' => 150,
        ]);
    })->throws(BusinessRuleException::class);

    test('throws exception when received + rejected exceeds ordered', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state([
                'quantity_ordered' => 100,
            ]), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $item = $grn->items->first();

        $this->service->updateItem($item, [
            'quantity_received' => 80,
            'quantity_rejected' => 30,
        ]);
    })->throws(BusinessRuleException::class, 'tidak boleh melebihi');
});

describe('Receiving Workflow', function () {
    test('starts receiving for draft GRN with items', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $receiving = $this->service->startReceiving($grn, $this->user->id);

        expect($receiving)->status->toBe(DocumentStatus::Receiving);
    });

    test('throws exception when starting receiving without items', function () {
        $grn = GoodsReceiptNote::factory()->create(['status' => DocumentStatus::Draft]);

        $this->service->startReceiving($grn, $this->user->id);
    })->throws(BusinessRuleException::class);

    test('completes GRN and updates inventory', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'product_id' => $this->product->id,
                'quantity' => 100,
                'quantity_received' => 0,
            ]), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $grn = GoodsReceiptNote::factory()
            ->for($po, 'purchaseOrder')
            ->has(GoodsReceiptNoteItem::factory()->state([
                'purchase_order_item_id' => $po->items->first()->id,
                'product_id' => $this->product->id,
                'quantity_ordered' => 50,
                'quantity_received' => 50,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Receiving,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockIn')
            ->once()
            ->withArgs(function ($product, $warehouse, $quantity, $unitPrice, $reference, $sourceType, $sourceId) use ($grn) {
                return $product instanceof \App\Models\Inventory\Product
                    && $warehouse instanceof \App\Models\Inventory\Warehouse
                    && $quantity === 50
                    && $sourceType === GoodsReceiptNote::class
                    && $sourceId === $grn->id;
            });

        // Recreate service with mocked dependency
        $service = app(GoodsReceiptNoteServiceInterface::class);

        $completed = $service->complete($grn, $this->user->id);

        expect($completed)
            ->status->toBe(DocumentStatus::Completed)
            ->completed_at->not->toBeNull();

        // PO item should be updated
        $po->refresh();
        expect((float) $po->items->first()->quantity_received)->toBe(50.0);
    });

    test('throws exception when completing non-receiving GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->complete($grn, $this->user->id);
    })->throws(StateTransitionException::class);

    test('cancels GRN in draft or receiving state', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $cancelled = $this->service->cancel($grn, $this->user->id);

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled)
            ->cancelled_at->not->toBeNull();
    });

    test('throws exception when canceling completed GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Completed]);

        $this->service->cancel($grn, $this->user->id);
    })->throws(StateTransitionException::class);
});

describe('Partial Receiving', function () {
    test('completes GRN with partial quantities', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'product_id' => $this->product->id,
                'quantity' => 100,
            ]), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $grn = GoodsReceiptNote::factory()
            ->for($po, 'purchaseOrder')
            ->has(GoodsReceiptNoteItem::factory()->state([
                'purchase_order_item_id' => $po->items->first()->id,
                'product_id' => $this->product->id,
                'quantity_ordered' => 100,
                'quantity_received' => 60,
                'quantity_rejected' => 5,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Receiving,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockIn')->once();

        $service = app(GoodsReceiptNoteServiceInterface::class);

        $completed = $service->complete($grn, $this->user->id);

        expect($completed->status)->toBe(DocumentStatus::Completed);

        // Only 60 should be added to PO received quantity
        $po->refresh();
        expect((float) $po->items->first()->quantity_received)->toBe(60.0);
    });

    test('does not create inventory movement for products not tracking inventory', function () {
        $product = Product::factory()->create(['track_inventory' => false]);

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'product_id' => $product->id,
                'quantity' => 100,
            ]), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $grn = GoodsReceiptNote::factory()
            ->for($po, 'purchaseOrder')
            ->has(GoodsReceiptNoteItem::factory()->state([
                'purchase_order_item_id' => $po->items->first()->id,
                'product_id' => $product->id,
                'quantity_ordered' => 100,
                'quantity_received' => 100,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Receiving,
                'warehouse_id' => $this->warehouse->id,
            ]);

        // Should not call stockIn for non-tracked products
        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldNotReceive('stockIn');

        $this->service->complete($grn, $this->user->id);
    });
});

describe('Query Methods', function () {
    test('gets GRNs for purchase order', function () {
        $po = PurchaseOrder::factory()->create();

        GoodsReceiptNote::factory()->count(3)->create(['purchase_order_id' => $po->id]);
        GoodsReceiptNote::factory()->create(); // Different PO

        $results = $this->service->getForPurchaseOrder($po);

        expect($results)->toHaveCount(3);
    });
});

describe('Inventory Accounting Strategy', function () {
    test('complete in perpetual mode triggers inventory accounting strategy', function () {
        // Seed chart of accounts (required for PerpetualInventoryStrategy JE creation)
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

        // Flush once() cache to pick up new policy
        Once::flush();

        // Set to perpetual inventory mode
        AccountingPolicy::query()->update(['inventory_method' => 'perpetual']);

        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'product_id' => $product->id,
                'quantity' => 50,
                'quantity_received' => 0,
            ]), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $grn = GoodsReceiptNote::factory()
            ->for($po, 'purchaseOrder')
            ->has(GoodsReceiptNoteItem::factory()->state([
                'purchase_order_item_id' => $po->items->first()->id,
                'product_id' => $product->id,
                'quantity_ordered' => 50,
                'quantity_received' => 50,
                'unit_price' => 100000,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Receiving,
                'warehouse_id' => $this->warehouse->id,
            ]);

        // Mock inventory service so stock-in doesn't fail
        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockIn')->once();

        $service = app(GoodsReceiptNoteServiceInterface::class);
        $service->complete($grn, $this->user->id);

        // Verify the inventory accounting JE was created (Dr Inventory / Cr GRNI)
        $journalEntry = JournalEntry::where('source_type', GoodsReceiptNote::class)
            ->where('source_id', $grn->id)
            ->first();

        expect($journalEntry)->not->toBeNull()
            ->and($journalEntry->is_posted)->toBeTrue()
            ->and($journalEntry->isBalanced())->toBeTrue()
            ->and($journalEntry->getTotalDebit())->toBe(5_000_000); // 50 * 100,000
    });

    test('complete in hybrid mode does not create GRNI journal entry', function () {
        // Seed chart of accounts
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

        Once::flush();

        // Default is hybrid — no GRNI JE should be created
        AccountingPolicy::query()->update(['inventory_method' => 'hybrid']);

        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'product_id' => $product->id,
                'quantity' => 50,
                'quantity_received' => 0,
            ]), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $grn = GoodsReceiptNote::factory()
            ->for($po, 'purchaseOrder')
            ->has(GoodsReceiptNoteItem::factory()->state([
                'purchase_order_item_id' => $po->items->first()->id,
                'product_id' => $product->id,
                'quantity_ordered' => 50,
                'quantity_received' => 50,
                'unit_price' => 100000,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Receiving,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $inventoryService = $this->mock(InventoryServiceInterface::class);
        $inventoryService->shouldReceive('stockIn')->once();

        $service = app(GoodsReceiptNoteServiceInterface::class);
        $service->complete($grn, $this->user->id);

        // No JE should be created for hybrid mode
        $journalEntry = JournalEntry::where('source_type', GoodsReceiptNote::class)
            ->where('source_id', $grn->id)
            ->first();

        expect($journalEntry)->toBeNull();
    });
});
