<?php

use App\Contracts\Services\Domains\InventoryServiceInterface;
use App\Domain\Sales\SalesReturns\Handlers\InventoryReturnHandler;
use App\Domain\Sales\SalesReturns\Handlers\JournalEntryHandler;
use App\Domain\Sales\SalesReturns\Handlers\SalesReturnApprovalPipeline;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('InventoryReturnHandler', function () {

    it('should handle returns when warehouse is specified', function () {
        $warehouse = Warehouse::factory()->create();
        $salesReturn = SalesReturn::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => DocumentStatus::Approved,
        ]);

        $handler = app(InventoryReturnHandler::class);

        expect($handler->shouldHandle($salesReturn))->toBeTrue();
    });

    it('should NOT handle returns when no warehouse specified', function () {
        $salesReturn = SalesReturn::factory()->create([
            'warehouse_id' => null,
            'status' => DocumentStatus::Approved,
        ]);

        $handler = app(InventoryReturnHandler::class);

        expect($handler->shouldHandle($salesReturn))->toBeFalse();
    });

    it('has priority 10 (runs before journal entry)', function () {
        $handler = app(InventoryReturnHandler::class);

        expect($handler->priority())->toBe(10);
    });

    it('processes stock-in for products with inventory tracking', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'warehouse_id' => $warehouse->id,
            'status' => DocumentStatus::Approved,
        ]);

        SalesReturnItem::factory()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]);

        $salesReturn->refresh();

        // Mock inventory service to verify it's called
        $inventoryMock = Mockery::mock(InventoryServiceInterface::class);
        $inventoryMock->shouldReceive('stockIn')
            ->once()
            ->withArgs(function ($p, $w, $qty, $price, $desc, $sourceType, $sourceId) use ($product, $warehouse, $salesReturn) {
                return $p->id === $product->id
                    && $w->id === $warehouse->id
                    && $qty === 5
                    && str_contains($desc, $salesReturn->return_number);
            });

        $handler = new InventoryReturnHandler($inventoryMock);
        $handler->handle($salesReturn);
    });

});

describe('JournalEntryHandler', function () {

    beforeEach(function () {
        // Seed required accounts
        Account::factory()->create(['code' => '4-2001', 'name' => 'Sales Returns', 'type' => 'revenue']);
        Account::factory()->create(['code' => '1-1100', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        Account::factory()->create(['code' => '2-1200', 'name' => 'PPN Keluaran', 'type' => 'liability']);
    });

    it('has priority 20 (runs after inventory)', function () {
        $handler = app(JournalEntryHandler::class);

        expect($handler->priority())->toBe(20);
    });

    it('always handles approved sales returns', function () {
        $salesReturn = SalesReturn::factory()->create([
            'status' => DocumentStatus::Approved,
        ]);

        $handler = app(JournalEntryHandler::class);

        expect($handler->shouldHandle($salesReturn))->toBeTrue();
    });

    it('creates journal entry on handle', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'status' => DocumentStatus::Approved,
            'subtotal' => 1000,
            'tax_amount' => 110,
            'total_amount' => 1110,
        ]);

        $handler = app(JournalEntryHandler::class);
        $handler->handle($salesReturn);

        $salesReturn->refresh();

        expect($salesReturn->journal_entry_id)->not->toBeNull();
    });

});

describe('SalesReturnApprovalPipeline', function () {

    it('can add handlers', function () {
        $pipeline = new SalesReturnApprovalPipeline;

        $inventoryHandler = app(InventoryReturnHandler::class);
        $journalHandler = app(JournalEntryHandler::class);

        $pipeline->addHandler($inventoryHandler);
        $pipeline->addHandler($journalHandler);

        expect($pipeline->count())->toBe(2);
    });

    it('sorts handlers by priority', function () {
        $pipeline = new SalesReturnApprovalPipeline;

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
        $salesReturn = SalesReturn::factory()->create([
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

        $pipeline = new SalesReturnApprovalPipeline;
        $pipeline->addHandler($inventoryMock);
        $pipeline->addHandler($journalMock);

        $pipeline->process($salesReturn);
    });

    it('is resolved from container with handlers pre-configured', function () {
        $pipeline = app(SalesReturnApprovalPipeline::class);

        // Pipeline should have 2 handlers (inventory and journal)
        expect($pipeline->count())->toBe(2);

        $handlers = $pipeline->getHandlers();
        expect($handlers[0])->toBeInstanceOf(InventoryReturnHandler::class);
        expect($handlers[1])->toBeInstanceOf(JournalEntryHandler::class);
    });

});
