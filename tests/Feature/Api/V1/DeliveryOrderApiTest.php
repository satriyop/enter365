<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('Delivery Order CRUD', function () {

    it('can list all delivery orders', function () {
        DeliveryOrder::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/delivery-orders');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/delivery-orders');
        $response->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'status' => ['value', 'label', 'color', 'is_terminal', 'is_editable'],
                    ]
                ]
            ]);
    });

    it('can filter delivery orders by status', function () {
        DeliveryOrder::factory()->draft()->count(3)->create();
        DeliveryOrder::factory()->shipped()->count(2)->create();

        $response = $this->getJson('/api/v1/delivery-orders?status=draft');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter delivery orders by contact', function () {
        $contact = Contact::factory()->customer()->create();
        DeliveryOrder::factory()->forContact($contact)->count(2)->create();
        DeliveryOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/delivery-orders?contact_id={$contact->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter delivery orders by date range', function () {
        DeliveryOrder::factory()->create(['do_date' => '2025-12-01']);
        DeliveryOrder::factory()->create(['do_date' => '2025-12-15']);
        DeliveryOrder::factory()->create(['do_date' => '2025-12-30']);

        $response = $this->getJson('/api/v1/delivery-orders?start_date=2025-12-10&end_date=2025-12-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search delivery orders', function () {
        $do = DeliveryOrder::factory()->create([
            'driver_name' => 'Budi Santoso',
        ]);
        DeliveryOrder::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/delivery-orders?search=Budi');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a delivery order with items', function () {
        $contact = Contact::factory()->customer()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/delivery-orders', [
            'contact_id' => $contact->id,
            'do_date' => '2025-12-26',
            'shipping_address' => 'Jl. Sudirman No. 123',
            'shipping_method' => 'courier',
            'driver_name' => 'Budi',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Solar Panel 300W',
                    'quantity' => 10,
                    'unit' => 'pcs',
                ],
                [
                    'description' => 'Mounting Bracket',
                    'quantity' => 20,
                    'unit' => 'set',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(2, 'data.items');

        expect($response->json('data.do_number'))->toStartWith('DO-');
    });

    it('validates required fields when creating delivery order', function () {
        $response = $this->postJson('/api/v1/delivery-orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'do_date', 'items']);
    });

    it('can show a single delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->create();
        DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->count(2)->create();

        $response = $this->getJson("/api/v1/delivery-orders/{$deliveryOrder->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $deliveryOrder->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();
        DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create();

        $response = $this->putJson("/api/v1/delivery-orders/{$deliveryOrder->id}", [
            'shipping_address' => 'New Address',
            'driver_name' => 'New Driver',
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 5,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.shipping_address', 'New Address')
            ->assertJsonPath('data.driver_name', 'New Driver')
            ->assertJsonCount(1, 'data.items');
    });

    it('cannot update a non-draft delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->confirmed()->create();

        $response = $this->putJson("/api/v1/delivery-orders/{$deliveryOrder->id}", [
            'driver_name' => 'New Driver',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/delivery-orders/{$deliveryOrder->id}");

        $response->assertOk();
        $this->assertSoftDeleted('delivery_orders', ['id' => $deliveryOrder->id]);
    });

    it('cannot delete a non-draft delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->confirmed()->create();

        $response = $this->deleteJson("/api/v1/delivery-orders/{$deliveryOrder->id}");

        $response->assertUnprocessable();
    });
});

describe('Delivery Order Workflow', function () {

    it('can confirm a draft delivery order with items', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();
        DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->count(2)->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'confirmed');

        $deliveryOrder->refresh();
        expect($deliveryOrder->confirmed_at)->not->toBeNull();
        expect($deliveryOrder->confirmed_by)->toBe($this->user->id);
    });

    it('cannot confirm a delivery order without items', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/confirm");

        $response->assertUnprocessable();
    });

    it('can ship a confirmed delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->confirmed()->create();
        DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/ship", [
            'tracking_number' => 'TRK12345678',
            'driver_name' => 'Budi',
            'vehicle_number' => 'B 1234 ABC',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'shipped')
            ->assertJsonPath('data.tracking_number', 'TRK12345678');

        $deliveryOrder->refresh();
        expect($deliveryOrder->shipped_at)->not->toBeNull();
    });

    it('cannot ship a non-confirmed delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/ship");

        $response->assertUnprocessable();
    });

    it('can mark a shipped delivery order as delivered', function () {
        $deliveryOrder = DeliveryOrder::factory()->shipped()->create();
        DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create([
            'quantity' => 10,
            'quantity_delivered' => 0,
        ]);

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/deliver", [
            'received_by' => 'Ahmad',
            'delivery_notes' => 'Received in good condition',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'delivered')
            ->assertJsonPath('data.received_by', 'Ahmad');

        $deliveryOrder->refresh();
        expect($deliveryOrder->delivered_at)->not->toBeNull();

        // Check items are marked as fully delivered
        $deliveryOrder->items->each(function ($item) {
            expect((float) $item->quantity_delivered)->toBe((float) $item->quantity);
        });
    });

    it('cannot deliver a non-shipped delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->confirmed()->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/deliver");

        $response->assertUnprocessable();
    });

    it('can cancel a draft delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/cancel", [
            'reason' => 'Customer cancelled order',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('can cancel a confirmed delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->confirmed()->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/cancel", [
            'reason' => 'Out of stock',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('cannot cancel a shipped delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->shipped()->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/cancel");

        $response->assertUnprocessable();
    });
});

describe('Delivery Order Progress', function () {

    it('can update delivery progress for shipped order', function () {
        $deliveryOrder = DeliveryOrder::factory()->shipped()->create();
        $item1 = DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create([
            'quantity' => 10,
            'quantity_delivered' => 0,
        ]);
        $item2 = DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create([
            'quantity' => 20,
            'quantity_delivered' => 0,
        ]);

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/update-progress", [
            'items' => [
                ['item_id' => $item1->id, 'quantity_delivered' => 5],
                ['item_id' => $item2->id, 'quantity_delivered' => 10],
            ],
        ]);

        $response->assertOk();

        $item1->refresh();
        $item2->refresh();

        expect((float) $item1->quantity_delivered)->toBe(5.0);
        expect((float) $item2->quantity_delivered)->toBe(10.0);
    });

    it('auto marks as delivered when all items fully delivered', function () {
        $deliveryOrder = DeliveryOrder::factory()->shipped()->create();
        $item = DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create([
            'quantity' => 10,
            'quantity_delivered' => 0,
        ]);

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/update-progress", [
            'items' => [
                ['item_id' => $item->id, 'quantity_delivered' => 10],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'delivered');
    });

    it('cannot deliver more than ordered quantity', function () {
        $deliveryOrder = DeliveryOrder::factory()->shipped()->create();
        $item = DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create([
            'quantity' => 10,
            'quantity_delivered' => 0,
        ]);

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/update-progress", [
            'items' => [
                ['item_id' => $item->id, 'quantity_delivered' => 15],
            ],
        ]);

        $response->assertStatus(409);
    });

    it('cannot update progress for non-shipped order', function () {
        $deliveryOrder = DeliveryOrder::factory()->confirmed()->create();
        $item = DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/update-progress", [
            'items' => [
                ['item_id' => $item->id, 'quantity_delivered' => 5],
            ],
        ]);

        $response->assertUnprocessable();
    });
});

describe('Delivery Order from Invoice', function () {

    it('can create delivery order from invoice', function () {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->for($invoice)->count(2)->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/create-delivery-order", [
            'do_date' => '2025-12-26',
            'shipping_address' => 'Customer Address',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invoice_id', $invoice->id)
            ->assertJsonPath('data.contact_id', $invoice->contact_id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can list delivery orders for an invoice', function () {
        $invoice = Invoice::factory()->create();
        DeliveryOrder::factory()->forInvoice($invoice)->count(2)->create();
        DeliveryOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/delivery-orders");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('Delivery Order Duplicate', function () {

    it('can duplicate a delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->delivered()->create();
        DeliveryOrderItem::factory()->forDeliveryOrder($deliveryOrder)->fullyDelivered()->count(2)->create();

        $response = $this->postJson("/api/v1/delivery-orders/{$deliveryOrder->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(2, 'data.items');

        // New DO number
        expect($response->json('data.do_number'))->not->toBe($deliveryOrder->do_number);

        // Items should have zero quantity_delivered
        expect((float) $response->json('data.items.0.quantity_delivered'))->toBe(0.0);
    });
});

describe('Delivery Order Statistics', function () {

    it('returns delivery order statistics', function () {
        DeliveryOrder::factory()->draft()->count(2)->create();
        DeliveryOrder::factory()->confirmed()->count(3)->create();
        DeliveryOrder::factory()->shipped()->count(1)->create();
        DeliveryOrder::factory()->delivered()->create(['delivered_at' => now()]);

        $response = $this->getJson('/api/v1/delivery-orders-statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_count',
                'by_status',
                'pending_delivery',
                'delivered_this_month',
            ]);

        expect($response->json('total_count'))->toBe(7);
        expect($response->json('by_status.draft'))->toBe(2);
        expect($response->json('by_status.confirmed'))->toBe(3);
        expect($response->json('pending_delivery'))->toBe(4); // confirmed + shipped
    });
});

describe('Delivery Order with Warehouse', function () {

    it('can create delivery order with warehouse', function () {
        $contact = Contact::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();

        $response = $this->postJson('/api/v1/delivery-orders', [
            'contact_id' => $contact->id,
            'warehouse_id' => $warehouse->id,
            'do_date' => '2025-12-26',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 10,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.warehouse_id', $warehouse->id);
    });
});
