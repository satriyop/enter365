<?php

use App\Contracts\Services\Domains\InventoryServiceInterface;
use App\Domain\Purchasing\PurchaseReturns\Handlers\InventoryReturnHandler;
use App\Domain\Purchasing\PurchaseReturns\Handlers\JournalEntryHandler;
use App\Domain\Purchasing\PurchaseReturns\Handlers\PurchaseReturnApprovalPipeline;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('InventoryReturnHandler', function () {

    it('should handle returns when warehouse is specified', function () {
        $warehouse = Warehouse::factory()->create();
        $purchaseReturn = PurchaseReturn::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => DocumentStatus::Approved,
        ]);

        $handler = app(InventoryReturnHandler::class);

        expect($handler->shouldHandle($purchaseReturn))->toBeTrue();
    });

    it('should NOT handle returns when no warehouse specified', function () {
        $purchaseReturn = PurchaseReturn::factory()->create([
            'warehouse_id' => null,
            'status' => DocumentStatus::Approved,
        ]);

        $handler = app(InventoryReturnHandler::class);

        expect($handler->shouldHandle($purchaseReturn))->toBeFalse();
    });

    it('has priority 10 (runs before journal entry)', function () {
        $handler = app(InventoryReturnHandler::class);

        expect($handler->priority())->toBe(10);
    });

    it('processes stock-out for products with inventory tracking', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $bill = Bill::factory()->create(['status' => DocumentStatus::Received]);
        $purchaseReturn = PurchaseReturn::factory()->create([
            'bill_id' => $bill->id,
            'contact_id' => $bill->contact_id,
            'warehouse_id' => $warehouse->id,
            'status' => DocumentStatus::Approved,
        ]);

        PurchaseReturnItem::factory()->create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]);

        $purchaseReturn->refresh();

        // Mock inventory service to verify it's called
        $inventoryMock = Mockery::mock(InventoryServiceInterface::class);
        $inventoryMock->shouldReceive('stockOut')
            ->once()
            ->withArgs(function ($p, $w, $qty, $desc, $sourceType, $sourceId) use ($product, $warehouse, $purchaseReturn) {
                return $p->id === $product->id
                    && $w->id === $warehouse->id
                    && $qty === 5
                    && str_contains($desc, $purchaseReturn->return_number);
            });

        $handler = new InventoryReturnHandler($inventoryMock);
        $handler->handle($purchaseReturn);
    });

});

describe('JournalEntryHandler', function () {

    beforeEach(function () {
        // Seed required accounts
        Account::factory()->create(['code' => '5-2001', 'name' => 'Purchase Returns', 'type' => 'expense']);
        Account::factory()->create(['code' => '2-1100', 'name' => 'Accounts Payable', 'type' => 'liability']);
        Account::factory()->create(['code' => '1-1300', 'name' => 'PPN Masukan', 'type' => 'asset']);
    });

    it('has priority 20 (runs after inventory)', function () {
        $handler = app(JournalEntryHandler::class);

        expect($handler->priority())->toBe(20);
    });

    it('always handles approved purchase returns', function () {
        $purchaseReturn = PurchaseReturn::factory()->create([
            'status' => DocumentStatus::Approved,
        ]);

        $handler = app(JournalEntryHandler::class);

        expect($handler->shouldHandle($purchaseReturn))->toBeTrue();
    });

    it('creates journal entry on handle', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $bill = Bill::factory()->create(['status' => DocumentStatus::Received]);
        $purchaseReturn = PurchaseReturn::factory()->create([
            'bill_id' => $bill->id,
            'contact_id' => $bill->contact_id,
            'status' => DocumentStatus::Approved,
            'subtotal' => 1000,
            'tax_amount' => 110,
            'total_amount' => 1110,
        ]);

        $handler = app(JournalEntryHandler::class);
        $handler->handle($purchaseReturn);

        $purchaseReturn->refresh();

        expect($purchaseReturn->journal_entry_id)->not->toBeNull();
    });

});

describe('PurchaseReturnApprovalPipeline', function () {

    it('can add handlers', function () {
        $pipeline = new PurchaseReturnApprovalPipeline;

        $inventoryHandler = app(InventoryReturnHandler::class);
        $journalHandler = app(JournalEntryHandler::class);

        $pipeline->addHandler($inventoryHandler);
        $pipeline->addHandler($journalHandler);

        expect($pipeline->count())->toBe(2);
    });

    it('sorts handlers by priority', function () {
        $pipeline = new PurchaseReturnApprovalPipeline;

        $inventoryHandler = app(InventoryReturnHandler::class); // priority 10
        $journalHandler = app(JournalEntryHandler::class); // priority 20

        // Add in reverse order
        $pipeline->addHandler($journalHandler);
        $pipeline->addHandler($inventoryHandler);

        $handlers = $pipeline->getHandlers();

        // First should be inventory (priority 10)
        expect($handlers[0]->priority())->toBe(10);
        // Second should be journal (priority 20)
        expect($handlers[1]->priority())->toBe(20);
    });

    it('only runs handlers that should handle the return', function () {
        $purchaseReturn = PurchaseReturn::factory()->create([
            'warehouse_id' => null, // No warehouse - inventory handler should skip
            'status' => DocumentStatus::Approved,
        ]);

        $inventoryMock = Mockery::mock(InventoryReturnHandler::class);
        $inventoryMock->shouldReceive('shouldHandle')->once()->andReturn(false);
        $inventoryMock->shouldReceive('priority')->andReturn(10);
        // handle() should NOT be called since shouldHandle returns false

        $journalMock = Mockery::mock(JournalEntryHandler::class);
        $journalMock->shouldReceive('shouldHandle')->once()->andReturn(true);
        $journalMock->shouldReceive('handle')->once();
        $journalMock->shouldReceive('priority')->andReturn(20);

        $pipeline = new PurchaseReturnApprovalPipeline;
        $pipeline->addHandler($inventoryMock);
        $pipeline->addHandler($journalMock);

        $pipeline->process($purchaseReturn);
    });

    it('is resolved from container with handlers pre-configured', function () {
        $pipeline = app(PurchaseReturnApprovalPipeline::class);

        // Pipeline should have 2 handlers (inventory and journal)
        expect($pipeline->count())->toBe(2);

        $handlers = $pipeline->getHandlers();
        expect($handlers[0])->toBeInstanceOf(InventoryReturnHandler::class);
        expect($handlers[1])->toBeInstanceOf(JournalEntryHandler::class);
    });

});
