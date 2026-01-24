<?php

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate user
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('GRN CRUD', function () {

    it('can list all goods receipt notes', function () {
        GoodsReceiptNote::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/goods-receipt-notes');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/goods-receipt-notes');
        $response->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'grn_number',
                        'status' => ['value', 'label', 'color'],
                        'receipt_date',
                    ],
                ],
            ]);
    });

    it('can filter goods receipt notes by status', function () {
        GoodsReceiptNote::factory()->draft()->count(2)->create();
        GoodsReceiptNote::factory()->receiving()->count(3)->create();

        $response = $this->getJson('/api/v1/goods-receipt-notes?status=receiving');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter goods receipt notes by warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        GoodsReceiptNote::factory()->forWarehouse($warehouse)->count(2)->create();
        GoodsReceiptNote::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/goods-receipt-notes?warehouse_id={$warehouse->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter goods receipt notes by purchase order', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        GoodsReceiptNote::factory()->forPurchaseOrder($po)->count(2)->create();
        GoodsReceiptNote::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/goods-receipt-notes?purchase_order_id={$po->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search goods receipt notes', function () {
        $grn = GoodsReceiptNote::factory()->create(['supplier_do_number' => 'DO-UNIQUE-123']);
        GoodsReceiptNote::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/goods-receipt-notes?search=DO-UNIQUE');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $grn->id);
    });

    it('can create a goods receipt note', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        $warehouse = Warehouse::factory()->create();

        $response = $this->postJson('/api/v1/goods-receipt-notes', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.purchase_order_id', $po->id)
            ->assertJsonPath('data.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.status.value', 'draft');
    });

    it('can show a goods receipt note', function () {
        $grn = GoodsReceiptNote::factory()->create();

        $response = $this->getJson("/api/v1/goods-receipt-notes/{$grn->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $grn->id)
            ->assertJsonPath('data.grn_number', $grn->grn_number);
    });

    it('can update a goods receipt note in draft status', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $response = $this->putJson("/api/v1/goods-receipt-notes/{$grn->id}", [
            'supplier_do_number' => 'DO-UPDATED-001',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.supplier_do_number', 'DO-UPDATED-001')
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('cannot update a goods receipt note in completed status', function () {
        $grn = GoodsReceiptNote::factory()->completed()->create();

        $response = $this->putJson("/api/v1/goods-receipt-notes/{$grn->id}", [
            'notes' => 'Updated notes',
        ]);

        $response->assertStatus(422);
    });

    it('can delete a goods receipt note in draft status', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/goods-receipt-notes/{$grn->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('goods_receipt_notes', ['id' => $grn->id]);
    });

    it('cannot delete a goods receipt note in receiving status', function () {
        $grn = GoodsReceiptNote::factory()->receiving()->create();

        $response = $this->deleteJson("/api/v1/goods-receipt-notes/{$grn->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('goods_receipt_notes', ['id' => $grn->id]);
    });

});

describe('Create GRN from Purchase Order', function () {

    it('can create grn from approved purchase order', function () {
        $warehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->approved()->create();
        $product = Product::factory()->create(['track_inventory' => true]);
        PurchaseOrderItem::factory()->for($po)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'quantity_received' => 0,
        ]);

        $response = $this->postJson("/api/v1/purchase-orders/{$po->id}/create-grn", [
            'warehouse_id' => $warehouse->id,
            'supplier_do_number' => 'DO-2024-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.purchase_order_id', $po->id)
            ->assertJsonPath('data.warehouse_id', $warehouse->id);

        // Verify items were created
        $grnId = $response->json('data.id');
        $this->assertDatabaseHas('goods_receipt_note_items', [
            'goods_receipt_note_id' => $grnId,
            'product_id' => $product->id,
            'quantity_ordered' => 100,
        ]);
    });

    it('cannot create grn from draft purchase order', function () {
        $warehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$po->id}/create-grn", [
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Purchase Order hanya dapat menerima barang dalam status approved.']);
    });

    it('creates grn items with remaining quantity only', function () {
        $warehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->partial()->create();
        $product = Product::factory()->create(['track_inventory' => true]);
        PurchaseOrderItem::factory()->for($po)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'quantity_received' => 60, // 40 remaining
        ]);

        $response = $this->postJson("/api/v1/purchase-orders/{$po->id}/create-grn", [
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertCreated();

        $grnId = $response->json('data.id');
        $this->assertDatabaseHas('goods_receipt_note_items', [
            'goods_receipt_note_id' => $grnId,
            'quantity_ordered' => 40, // Only remaining qty
        ]);
    });

    it('can get grns for a purchase order', function () {
        $po = PurchaseOrder::factory()->approved()->create();
        GoodsReceiptNote::factory()->forPurchaseOrder($po)->count(2)->create();
        GoodsReceiptNote::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/purchase-orders/{$po->id}/goods-receipt-notes");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

});

describe('GRN Item Management', function () {

    it('can update item with received quantity', function () {
        $grn = GoodsReceiptNote::factory()->receiving()->create();
        $item = GoodsReceiptNoteItem::factory()->forGoodsReceiptNote($grn)->create([
            'quantity_ordered' => 100,
            'quantity_received' => 0,
        ]);

        $response = $this->putJson("/api/v1/goods-receipt-notes/{$grn->id}/items/{$item->id}", [
            'quantity_received' => 95,
            'quantity_rejected' => 5,
            'rejection_reason' => 'Damaged during shipping',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity_received', 95)
            ->assertJsonPath('data.quantity_rejected', 5)
            ->assertJsonPath('data.rejection_reason', 'Damaged during shipping');
    });

    it('cannot update item with quantity exceeding ordered', function () {
        $grn = GoodsReceiptNote::factory()->receiving()->create();
        $item = GoodsReceiptNoteItem::factory()->forGoodsReceiptNote($grn)->create([
            'quantity_ordered' => 100,
        ]);

        $response = $this->putJson("/api/v1/goods-receipt-notes/{$grn->id}/items/{$item->id}", [
            'quantity_received' => 150, // Exceeds ordered
        ]);

        $response->assertStatus(409);
    });

    it('cannot update item from another grn', function () {
        $grn1 = GoodsReceiptNote::factory()->receiving()->create();
        $grn2 = GoodsReceiptNote::factory()->receiving()->create();
        $item = GoodsReceiptNoteItem::factory()->forGoodsReceiptNote($grn2)->create();

        $response = $this->putJson("/api/v1/goods-receipt-notes/{$grn1->id}/items/{$item->id}", [
            'quantity_received' => 50,
        ]);

        $response->assertStatus(404);
    });

});

describe('GRN Workflow', function () {

    it('can start receiving', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();
        GoodsReceiptNoteItem::factory()->forGoodsReceiptNote($grn)->count(3)->create();

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/start-receiving");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'receiving');
    });

    it('cannot start receiving without items', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/start-receiving");

        $response->assertStatus(409);
    });

    it('can complete grn and update inventory', function () {
        $warehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->approved()->create();
        $product = Product::factory()->create(['track_inventory' => true]);
        $poItem = PurchaseOrderItem::factory()->for($po)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'quantity_received' => 0,
        ]);

        $grn = GoodsReceiptNote::factory()
            ->forPurchaseOrder($po)
            ->forWarehouse($warehouse)
            ->receiving()
            ->create();

        GoodsReceiptNoteItem::factory()
            ->forGoodsReceiptNote($grn)
            ->forPurchaseOrderItem($poItem)
            ->create([
                'product_id' => $product->id,
                'quantity_ordered' => 100,
                'quantity_received' => 100,
            ]);

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        // Verify inventory movement was created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 100,
        ]);

        // Verify product stock was updated
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock)->not->toBeNull();
        expect($stock->quantity)->toBe(100);
    });

    it('cannot complete grn without received items', function () {
        $grn = GoodsReceiptNote::factory()->receiving()->create();
        GoodsReceiptNoteItem::factory()->forGoodsReceiptNote($grn)->create([
            'quantity_ordered' => 100,
            'quantity_received' => 0, // No items received
        ]);

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/complete");

        $response->assertStatus(422);
    });

    it('can cancel a grn', function () {
        $grn = GoodsReceiptNote::factory()->receiving()->create();

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('cannot cancel a completed grn', function () {
        $grn = GoodsReceiptNote::factory()->completed()->create();

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/cancel");

        $response->assertStatus(422);
    });

});

describe('GRN Integration with Purchase Order', function () {

    it('updates purchase order status after complete receiving', function () {
        $warehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->approved()->create();
        $product = Product::factory()->create(['track_inventory' => true]);
        $poItem = PurchaseOrderItem::factory()->for($po)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'quantity_received' => 0,
        ]);

        $grn = GoodsReceiptNote::factory()
            ->forPurchaseOrder($po)
            ->forWarehouse($warehouse)
            ->receiving()
            ->create();

        GoodsReceiptNoteItem::factory()
            ->forGoodsReceiptNote($grn)
            ->forPurchaseOrderItem($poItem)
            ->create([
                'product_id' => $product->id,
                'quantity_ordered' => 100,
                'quantity_received' => 100,
            ]);

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/complete");

        $response->assertOk();

        // Verify PO status changed to received
        $po->refresh();
        expect($po->status)->toBe(DocumentStatus::Received);
    });

    it('updates purchase order to partial status for partial receiving', function () {
        $warehouse = Warehouse::factory()->create();
        $po = PurchaseOrder::factory()->approved()->create();
        $product = Product::factory()->create(['track_inventory' => true]);
        $poItem = PurchaseOrderItem::factory()->for($po)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'quantity_received' => 0,
        ]);

        $grn = GoodsReceiptNote::factory()
            ->forPurchaseOrder($po)
            ->forWarehouse($warehouse)
            ->receiving()
            ->create();

        GoodsReceiptNoteItem::factory()
            ->forGoodsReceiptNote($grn)
            ->forPurchaseOrderItem($poItem)
            ->create([
                'product_id' => $product->id,
                'quantity_ordered' => 100,
                'quantity_received' => 60, // Only 60 of 100
            ]);

        $response = $this->postJson("/api/v1/goods-receipt-notes/{$grn->id}/complete");

        $response->assertOk();

        // Verify PO status changed to partial
        $po->refresh();
        expect($po->status)->toBe(DocumentStatus::Partial);

        // Verify PO item quantity_received updated
        $poItem->refresh();
        expect((int) $poItem->quantity_received)->toBe(60);
    });

});
