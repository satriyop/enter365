<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Purchase Order CRUD', function () {

    it('can list all purchase orders', function () {
        PurchaseOrder::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase-orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter purchase orders by status', function () {
        PurchaseOrder::factory()->draft()->count(2)->create();
        PurchaseOrder::factory()->approved()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase-orders?status=approved');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter purchase orders by contact', function () {
        $vendor = Contact::factory()->vendor()->create();
        PurchaseOrder::factory()->forContact($vendor)->count(2)->create();
        PurchaseOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/purchase-orders?contact_id={$vendor->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter purchase orders by date range', function () {
        PurchaseOrder::factory()->create(['po_date' => '2024-12-01']);
        PurchaseOrder::factory()->create(['po_date' => '2024-12-15']);
        PurchaseOrder::factory()->create(['po_date' => '2024-12-25']);

        $response = $this->getJson('/api/v1/purchase-orders?start_date=2024-12-10&end_date=2024-12-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter outstanding purchase orders', function () {
        PurchaseOrder::factory()->draft()->count(2)->create();
        PurchaseOrder::factory()->approved()->count(3)->create();
        PurchaseOrder::factory()->partial()->count(2)->create();
        PurchaseOrder::factory()->received()->count(1)->create();

        $response = $this->getJson('/api/v1/purchase-orders?outstanding_only=true');

        $response->assertOk()
            ->assertJsonCount(5, 'data'); // approved + partial
    });

    it('can search purchase orders', function () {
        PurchaseOrder::factory()->create(['subject' => 'MCB Components']);
        PurchaseOrder::factory()->create(['subject' => 'Busbar Materials']);
        PurchaseOrder::factory()->create(['reference' => 'REF-MCB-001']);

        $response = $this->getJson('/api/v1/purchase-orders?search=mcb');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a purchase order with items', function () {
        $vendor = Contact::factory()->vendor()->create();

        $response = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => '2024-12-25',
            'expected_date' => '2025-01-10',
            'subject' => 'MCB Components Order',
            'reference' => 'REF-001',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'MCB 16A',
                    'quantity' => 10,
                    'unit' => 'pcs',
                    'unit_price' => 150000,
                ],
                [
                    'description' => 'MCB 32A',
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 200000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.items');

        // Verify calculations: 1,500,000 + 1,000,000 = 2,500,000 subtotal
        // Tax: 2,500,000 * 11% = 275,000
        // Total: 2,775,000
        $response->assertJsonPath('data.subtotal', 2500000)
            ->assertJsonPath('data.tax_amount', 275000)
            ->assertJsonPath('data.total', 2775000);
    });

    it('validates required fields when creating purchase order', function () {
        $response = $this->postJson('/api/v1/purchase-orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'po_date', 'items']);
    });

    it('validates expected_date is after po_date', function () {
        $vendor = Contact::factory()->vendor()->create();

        $response = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => '2024-12-25',
            'expected_date' => '2024-12-20',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_date']);
    });

    it('can show a single purchase order with items', function () {
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->count(2)->create();

        $response = $this->getJson("/api/v1/purchase-orders/{$purchaseOrder->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $purchaseOrder->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->draft()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create();

        $response = $this->putJson("/api/v1/purchase-orders/{$purchaseOrder->id}", [
            'subject' => 'Updated Subject',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subject', 'Updated Subject')
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('cannot update non-draft purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();

        $response = $this->putJson("/api/v1/purchase-orders/{$purchaseOrder->id}", [
            'subject' => 'Should fail',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/purchase-orders/{$purchaseOrder->id}");

        $response->assertOk();
        $this->assertSoftDeleted('purchase_orders', ['id' => $purchaseOrder->id]);
    });

    it('cannot delete non-draft purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();

        $response = $this->deleteJson("/api/v1/purchase-orders/{$purchaseOrder->id}");

        $response->assertUnprocessable();
    });
});

describe('Purchase Order Workflow', function () {

    it('can submit a draft purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->draft()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $purchaseOrder->refresh();
        expect($purchaseOrder->submitted_at)->not->toBeNull();
    });

    it('cannot submit purchase order without items', function () {
        $purchaseOrder = PurchaseOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/submit");

        $response->assertUnprocessable();
    });

    it('can approve a submitted purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->submitted()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $purchaseOrder->refresh();
        expect($purchaseOrder->approved_at)->not->toBeNull();
    });

    it('cannot approve non-submitted purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/approve");

        $response->assertUnprocessable();
    });

    it('can reject a submitted purchase order with reason', function () {
        $purchaseOrder = PurchaseOrder::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/reject", [
            'reason' => 'Harga terlalu mahal',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Harga terlalu mahal');
    });

    it('cannot reject without reason', function () {
        $purchaseOrder = PurchaseOrder::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/reject", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });

    it('can cancel a purchase order with reason', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/cancel", [
            'reason' => 'Vendor tidak bisa memenuhi pesanan',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Vendor tidak bisa memenuhi pesanan');
    });

    it('cannot cancel without reason', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/cancel", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });
});

describe('Purchase Order Receiving', function () {

    it('can receive items for an approved purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();
        $item = PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create([
            'quantity' => 10,
            'quantity_received' => 0,
        ]);

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 5],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'partial');

        $item->refresh();
        expect((float) $item->quantity_received)->toBe(5.0);
    });

    it('updates status to received when fully received', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();
        $item = PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create([
            'quantity' => 10,
            'quantity_received' => 0,
        ]);

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.is_fully_received', true);
    });

    it('cannot receive more than remaining quantity', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();
        $item = PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create([
            'quantity' => 10,
            'quantity_received' => 5,
        ]);

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 10], // Only 5 remaining
            ],
        ]);

        $response->assertUnprocessable();
    });

    it('cannot receive items for non-approved purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->draft()->create();
        $item = PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
    });

    it('shows receiving progress', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create([
            'quantity' => 10,
            'quantity_received' => 5,
        ]);

        $response = $this->getJson("/api/v1/purchase-orders/{$purchaseOrder->id}");

        $response->assertOk();
        expect((float) $response->json('data.receiving_progress'))->toBe(50.0);
    });
});

describe('Purchase Order Conversion', function () {

    it('can convert received purchase order to bill', function () {
        $purchaseOrder = PurchaseOrder::factory()->received()->create([
            'subject' => 'MCB Components',
            'subtotal' => 5000000,
            'tax_amount' => 550000,
            'total' => 5550000,
        ]);
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->fullyReceived()->count(2)->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/convert-to-bill");

        $response->assertCreated()
            ->assertJsonStructure(['message', 'bill', 'purchase_order']);

        $purchaseOrder->refresh();
        expect($purchaseOrder->converted_to_bill_id)->not->toBeNull();
        expect($purchaseOrder->converted_at)->not->toBeNull();
    });

    it('can convert partial purchase order to bill', function () {
        $purchaseOrder = PurchaseOrder::factory()->partial()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->partiallyReceived(5)->create([
            'quantity' => 10,
        ]);

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/convert-to-bill");

        $response->assertCreated();

        // Bill should have received quantity
        $bill = Bill::find($response->json('bill.id'));
        expect((float) $bill->items->first()->quantity)->toBe(5.0);
    });

    it('cannot convert non-received purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/convert-to-bill");

        $response->assertUnprocessable();
    });

    it('cannot convert already converted purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->converted()->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/convert-to-bill");

        $response->assertUnprocessable();
    });
});

describe('Purchase Order Duplicate', function () {

    it('can duplicate a purchase order', function () {
        $purchaseOrder = PurchaseOrder::factory()->approved()->create([
            'subject' => 'Original Subject',
        ]);
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->count(2)->create();

        $response = $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subject', 'Original Subject')
            ->assertJsonCount(2, 'data.items');

        // New PO number
        expect($response->json('data.po_number'))->not->toBe($purchaseOrder->po_number);
        // Items should have zero quantity_received
        expect((int) $response->json('data.items.0.quantity_received'))->toBe(0);
    });
});

describe('Purchase Order Outstanding', function () {

    it('returns outstanding purchase orders', function () {
        PurchaseOrder::factory()->draft()->count(2)->create();
        PurchaseOrder::factory()->approved()->count(3)->create();
        PurchaseOrder::factory()->partial()->count(2)->create();
        PurchaseOrder::factory()->received()->count(1)->create();

        $response = $this->getJson('/api/v1/purchase-orders-outstanding');

        $response->assertOk()
            ->assertJsonCount(5, 'data'); // approved + partial
    });

    it('can filter outstanding by contact', function () {
        $vendor = Contact::factory()->vendor()->create();
        PurchaseOrder::factory()->forContact($vendor)->approved()->count(2)->create();
        PurchaseOrder::factory()->approved()->count(3)->create();

        $response = $this->getJson("/api/v1/purchase-orders-outstanding?contact_id={$vendor->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('Purchase Order Statistics', function () {

    it('returns purchase order statistics', function () {
        PurchaseOrder::factory()->draft()->count(2)->create();
        PurchaseOrder::factory()->submitted()->count(3)->create();
        PurchaseOrder::factory()->approved()->count(4)->create();
        PurchaseOrder::factory()->rejected()->count(1)->create();
        PurchaseOrder::factory()->partial()->count(2)->create();
        PurchaseOrder::factory()->received()->count(3)->create();
        PurchaseOrder::factory()->cancelled()->count(1)->create();

        $response = $this->getJson('/api/v1/purchase-orders-statistics');

        $response->assertOk()
            ->assertJsonPath('data.total', 16)
            ->assertJsonPath('data.by_status.draft', 2)
            ->assertJsonPath('data.by_status.submitted', 3)
            ->assertJsonPath('data.by_status.approved', 4)
            ->assertJsonPath('data.by_status.rejected', 1)
            ->assertJsonPath('data.by_status.partial', 2)
            ->assertJsonPath('data.by_status.received', 3)
            ->assertJsonPath('data.by_status.cancelled', 1);
    });

    it('can filter statistics by date range', function () {
        PurchaseOrder::factory()->create(['po_date' => '2024-12-01']);
        PurchaseOrder::factory()->create(['po_date' => '2024-12-15']);
        PurchaseOrder::factory()->create(['po_date' => '2024-12-25']);

        $response = $this->getJson('/api/v1/purchase-orders-statistics?start_date=2024-12-10&end_date=2024-12-20');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    });
});

describe('Purchase Order Calculations', function () {

    it('calculates subtotal from items', function () {
        $vendor = Contact::factory()->vendor()->create();

        $response = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'tax_rate' => 0,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 100000],
                ['description' => 'Item 2', 'quantity' => 3, 'unit_price' => 200000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', 800000); // 200000 + 600000
    });

    it('applies percentage discount', function () {
        $vendor = Contact::factory()->vendor()->create();

        $response = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 11,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 1000000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', 1000000)
            ->assertJsonPath('data.discount_amount', 100000)
            ->assertJsonPath('data.tax_amount', 99000)
            ->assertJsonPath('data.total', 999000);
    });
});

describe('Purchase Order with Products', function () {

    it('can create purchase order with product reference', function () {
        $vendor = Contact::factory()->vendor()->create();
        $product = Product::factory()->create([
            'name' => 'MCB 16A',
            'purchase_price' => 120000,
            'unit' => 'pcs',
        ]);

        $response = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 10,
                    'unit' => $product->unit,
                    'unit_price' => $product->purchase_price,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.description', 'MCB 16A');
    });
});
