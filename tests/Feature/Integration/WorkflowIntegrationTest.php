<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use App\Services\Inventory\StockOpnameService;
use App\Services\Manufacturing\WorkOrderService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Sales Workflow: Quotation → Invoice', function () {
    test('complete quotation lifecycle from draft to invoice', function () {
        $service = app(QuotationService::class);

        $quotation = Quotation::factory()->draft()->create([
            'valid_until' => now()->addDays(30),
        ]);

        QuotationItem::factory()->count(2)->create([
            'quotation_id' => $quotation->id,
        ]);

        // Submit
        $quotation = $service->submit($quotation, $this->user->id);
        expect($quotation->status)->toBe(DocumentStatus::Submitted)
            ->and($quotation->submitted_at)->not->toBeNull();

        // Approve
        $quotation = $service->approve($quotation, $this->user->id);
        expect($quotation->status)->toBe(DocumentStatus::Approved)
            ->and($quotation->approved_at)->not->toBeNull();

        // Convert to Invoice
        $invoice = $service->convertToInvoice($quotation);
        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice->contact_id)->toBe($quotation->contact_id)
            ->and($invoice->items)->toHaveCount(2);

        // Verify quotation marked as converted
        $quotation->refresh();
        expect($quotation->status)->toBe(DocumentStatus::Converted)
            ->and($quotation->converted_to_invoice_id)->toBe($invoice->id);
    });
});

describe('Purchasing Workflow: PO → Bill', function () {
    test('purchase order state transitions from draft to approved', function () {
        $service = app(PurchaseOrderService::class);

        $po = PurchaseOrder::factory()->draft()->create();
        PurchaseOrderItem::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
        ]);

        // Submit
        $po = $service->submit($po, $this->user->id);
        expect($po->status)->toBe(DocumentStatus::Submitted)
            ->and($po->submitted_at)->not->toBeNull();

        // Approve
        $po = $service->approve($po, $this->user->id);
        expect($po->status)->toBe(DocumentStatus::Approved)
            ->and($po->approved_at)->not->toBeNull();
    });

    test('received PO converts to bill', function () {
        $service = app(PurchaseOrderService::class);

        $po = PurchaseOrder::factory()->received()->create();
        PurchaseOrderItem::factory()->fullyReceived()->count(2)->create([
            'purchase_order_id' => $po->id,
        ]);

        $bill = $service->convertToBill($po);
        expect($bill)->toBeInstanceOf(Bill::class)
            ->and($bill->contact_id)->toBe($po->contact_id)
            ->and($bill->items)->toHaveCount(2);

        $po->refresh();
        expect($po->converted_to_bill_id)->toBe($bill->id)
            ->and($po->converted_at)->not->toBeNull();
    });
});

describe('Manufacturing Workflow: Work Order lifecycle', function () {
    test('complete work order with material reservation and consumption', function () {
        $service = app(WorkOrderService::class);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 150000,
        ]);

        $wo = WorkOrder::factory()->draft()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        WorkOrderItem::factory()->material()->create([
            'work_order_id' => $wo->id,
            'product_id' => $product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 0,
            'quantity_consumed' => 0,
            'unit_cost' => 150000,
            'total_estimated_cost' => 1500000,
        ]);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 150000,
        ]);

        // Confirm (reserves materials)
        $wo = $service->confirm($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::Confirmed);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->reserved_quantity)->toBe(10);

        // Start
        $wo = $service->start($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::InProgress);

        // Complete (consumes materials)
        $wo = $service->complete($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::Completed);

        // Verify inventory impact
        $stock->refresh();
        expect($stock->quantity)->toBe(90)
            ->and($stock->reserved_quantity)->toBe(0);

        // Verify inventory movement created
        $movement = InventoryMovement::where('reference_type', WorkOrder::class)
            ->where('reference_id', $wo->id)
            ->first();
        expect($movement)->not->toBeNull()
            ->and($movement->type)->toBe(InventoryMovement::TYPE_OUT)
            ->and($movement->quantity)->toBe(10);
    });
});

describe('Inventory Workflow: Stock Opname lifecycle', function () {
    test('complete stock opname from draft to approved with inventory adjustments', function () {
        $service = app(StockOpnameService::class);

        // Create required accounts for journal entry
        Account::factory()->create([
            'code' => '1-1400',
            'name' => 'Persediaan',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'is_active' => true,
        ]);
        Account::factory()->create([
            'code' => '5-2900',
            'name' => 'Penyesuaian Persediaan',
            'type' => Account::TYPE_EXPENSE,
            'subtype' => Account::SUBTYPE_OPERATING_EXPENSE,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'average_cost' => 100000,
            'total_value' => 5000000,
        ]);

        // Create
        $opname = $service->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->user->id,
        ]);
        expect($opname->status)->toBe(DocumentStatus::Draft);

        // Generate items (pulls from warehouse stock)
        $opname = $service->generateItems($opname);
        expect($opname->items)->toHaveCount(1);

        $opnameItem = $opname->items->first();
        expect($opnameItem->product_id)->toBe($product->id)
            ->and($opnameItem->system_quantity)->toEqual(50);

        // Start counting
        $opname = $service->startCounting($opname, $this->user->id);
        expect($opname->status)->toBe(DocumentStatus::Counting);

        // Simulate counting (physical count = 48, shortage of 2)
        $opnameItem->refresh();
        $opnameItem->update([
            'counted_quantity' => 48,
            'variance_quantity' => -2,
            'variance_value' => -200000,
            'counted_at' => now(),
        ]);

        // Submit for review
        $opname = $service->submitForReview($opname, $this->user->id);
        expect($opname->status)->toBe(DocumentStatus::Reviewed);

        // Approve (adjusts inventory)
        $opname = $service->approve($opname, $this->user->id);
        expect($opname->status)->toBe(DocumentStatus::Completed);

        // Verify stock adjusted
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(48);
    });
});
