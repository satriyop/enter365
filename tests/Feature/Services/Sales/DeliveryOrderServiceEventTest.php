<?php

declare(strict_types=1);

use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderCancelled;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderConfirmed;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderDelivered;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderShipped;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 100000]);

    $this->service = app(DeliveryOrderServiceInterface::class);
    $this->actingAs($this->user);
});

function createDraftDOWithItems(object $testContext): DeliveryOrder
{
    $do = DeliveryOrder::factory()
        ->for($testContext->contact, 'contact')
        ->for($testContext->warehouse, 'warehouse')
        ->create(['status' => DocumentStatus::Draft]);

    DeliveryOrderItem::factory()->create([
        'delivery_order_id' => $do->id,
        'product_id' => $testContext->product->id,
        'description' => 'Test Product',
        'quantity' => 5,
        'unit_price' => 50000,
    ]);

    return $do->fresh(['items']);
}

describe('DeliveryOrderService event dispatching', function () {
    it('dispatches DeliveryOrderConfirmed on confirm', function () {
        Event::fake([DeliveryOrderConfirmed::class]);

        $do = createDraftDOWithItems($this);
        $result = $this->service->confirm($do);

        Event::assertDispatched(DeliveryOrderConfirmed::class, function (DeliveryOrderConfirmed $event) use ($do) {
            return $event->deliveryOrderId === $do->id
                && $event->doNumber === $do->do_number
                && $event->customerId === $do->contact_id;
        });
    });

    it('dispatches DeliveryOrderShipped on ship', function () {
        $do = createDraftDOWithItems($this);
        $this->service->confirm($do);

        // Ensure stock exists for deduction
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 100000,
            'total_value' => 10000000,
        ]);

        Event::fake([DeliveryOrderShipped::class]);

        $this->service->ship($do->fresh(), ['tracking_number' => 'TRK-001']);

        Event::assertDispatched(DeliveryOrderShipped::class, function (DeliveryOrderShipped $event) use ($do) {
            return $event->deliveryOrderId === $do->id
                && $event->doNumber === $do->do_number;
        });
    });

    it('dispatches DeliveryOrderDelivered on deliver', function () {
        $do = createDraftDOWithItems($this);
        $this->service->confirm($do);

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 100000,
            'total_value' => 10000000,
        ]);

        $this->service->ship($do->fresh());

        Event::fake([DeliveryOrderDelivered::class]);

        $this->service->deliver($do->fresh());

        Event::assertDispatched(DeliveryOrderDelivered::class, function (DeliveryOrderDelivered $event) use ($do) {
            return $event->deliveryOrderId === $do->id
                && $event->doNumber === $do->do_number
                && $event->customerId === $do->contact_id;
        });
    });

    it('dispatches DeliveryOrderCancelled on cancel', function () {
        Event::fake([DeliveryOrderCancelled::class]);

        $do = createDraftDOWithItems($this);
        $this->service->cancel($do, 'Test cancellation');

        Event::assertDispatched(DeliveryOrderCancelled::class, function (DeliveryOrderCancelled $event) use ($do) {
            return $event->deliveryOrderId === $do->id
                && $event->reason === 'Test cancellation';
        });
    });
});
