<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Accounting\Strategies\COGS\COGSOnDeliveryStrategy;
use App\Services\Accounting\Strategies\COGS\COGSOnInvoiceStrategy;
use App\Services\Accounting\Strategies\COGS\ManualCOGSStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    // Seed required data
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Mock JournalService
    $this->journalServiceMock = Mockery::mock(JournalServiceInterface::class);
    $this->app->instance(JournalServiceInterface::class, $this->journalServiceMock);
});

describe('COGSOnInvoiceStrategy', function () {

    beforeEach(function () {
        $this->strategy = new COGSOnInvoiceStrategy($this->journalServiceMock);
    });

    it('returns identifier "on_invoice"', function () {
        expect($this->strategy->getIdentifier())->toBe('on_invoice');
    });

    it('calculates COGS for invoice with inventory items', function () {
        // Create products with purchase prices
        $product1 = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $product2 = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 50000,
        ]);

        // Create invoice with items
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product1)->create([
            'quantity' => 5,
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product2)->create([
            'quantity' => 10,
        ]);

        $invoice->load('items.product');

        // Calculate COGS: (100000 * 5) + (50000 * 10) = 500000 + 500000 = 1000000
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(1000000);
    });

    it('skips service items in COGS calculation', function () {
        $inventoryProduct = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $serviceProduct = Product::factory()->service()->create([
            'track_inventory' => false,
            'purchase_price' => 200000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($inventoryProduct)->create([
            'quantity' => 5,
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($serviceProduct)->create([
            'quantity' => 3,
        ]);

        $invoice->load('items.product');

        // Only inventory product should be counted: 100000 * 5 = 500000
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(500000);
    });

    it('skips items without product_id in COGS calculation', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create();

        // Item with product
        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product)->create([
            'quantity' => 5,
        ]);

        // Item without product (custom item)
        InvoiceItem::factory()->forInvoice($invoice)->create([
            'product_id' => null,
            'quantity' => 10,
            'unit_price' => 50000,
        ]);

        $invoice->load('items.product');

        // Only item with product should be counted: 100000 * 5 = 500000
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(500000);
    });

    it('returns zero when invoice has no inventory items', function () {
        $serviceProduct = Product::factory()->service()->create([
            'track_inventory' => false,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($serviceProduct)->create([
            'quantity' => 5,
        ]);

        $invoice->load('items.product');

        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(0);
    });

    it('handles products with zero purchase price', function () {
        $productWithPrice = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $productWithZeroPrice = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 0,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($productWithPrice)->create([
            'quantity' => 5,
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($productWithZeroPrice)->create([
            'quantity' => 10,
        ]);

        $invoice->load('items.product');

        // Only product with non-zero price should be counted: 100000 * 5 = 500000
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(500000);
    });

    it('creates journal entry on invoice post with correct structure', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2025-001',
            'invoice_date' => now()->startOfDay(),
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product)->create([
            'quantity' => 10,
        ]);

        $invoice->load('items.product');

        // Mock journal entry creation
        $mockJournalEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) use ($invoice) {
                return $data['entry_date'] === $invoice->invoice_date->toDateString()
                    && str_contains($data['description'], $invoice->invoice_number)
                    && $data['reference'] === $invoice->invoice_number
                    && $data['source_type'] === Invoice::class
                    && $data['source_id'] === $invoice->id
                    && count($data['lines']) === 2;
            }), true)
            ->andReturn($mockJournalEntry);

        $result = $this->strategy->onInvoicePost($invoice);

        expect($result)->toBe($mockJournalEntry);
    });

    it('creates journal entry with correct account codes and amounts', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2025-002',
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product)->create([
            'quantity' => 5,
        ]);

        $invoice->load('items.product');

        $expectedCOGS = 500000;
        $cogsAccount = config('accounting.default_accounts.cogs', '5-1001');
        $inventoryAccount = config('accounting.default_accounts.inventory', '1-1400');

        $mockJournalEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) use ($expectedCOGS, $cogsAccount, $inventoryAccount) {
                if (count($data['lines']) !== 2) {
                    return false;
                }

                // Check COGS debit line
                $cogsLine = $data['lines'][0];
                if ($cogsLine['account_code'] !== $cogsAccount
                    || $cogsLine['debit'] !== $expectedCOGS
                    || $cogsLine['credit'] !== 0) {
                    return false;
                }

                // Check Inventory credit line
                $inventoryLine = $data['lines'][1];
                if ($inventoryLine['account_code'] !== $inventoryAccount
                    || $inventoryLine['debit'] !== 0
                    || $inventoryLine['credit'] !== $expectedCOGS) {
                    return false;
                }

                return true;
            }), true)
            ->andReturn($mockJournalEntry);

        $result = $this->strategy->onInvoicePost($invoice);

        expect($result)->toBe($mockJournalEntry);
    });

    it('returns null when COGS is zero on invoice post', function () {
        $serviceProduct = Product::factory()->service()->create([
            'track_inventory' => false,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($serviceProduct)->create([
            'quantity' => 5,
        ]);

        $invoice->load('items.product');

        // Should not call journal service when COGS is zero
        $this->journalServiceMock->shouldNotReceive('createEntry');

        $result = $this->strategy->onInvoicePost($invoice);

        expect($result)->toBeNull();
    });

    it('returns null on delivery ship', function () {
        $deliveryOrder = Mockery::mock(DeliveryOrder::class);

        $result = $this->strategy->onDeliveryShip($deliveryOrder);

        expect($result)->toBeNull();
    });
});

describe('COGSOnDeliveryStrategy', function () {

    beforeEach(function () {
        $invoiceStrategy = new COGSOnInvoiceStrategy($this->journalServiceMock);
        $this->strategy = new COGSOnDeliveryStrategy($invoiceStrategy, $this->journalServiceMock);
    });

    it('returns identifier "on_delivery"', function () {
        expect($this->strategy->getIdentifier())->toBe('on_delivery');
    });

    it('returns null on invoice post', function () {
        $invoice = Invoice::factory()->create();

        $result = $this->strategy->onInvoicePost($invoice);

        expect($result)->toBeNull();
    });

    it('creates journal entry on delivery ship with correct structure', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $deliveryOrder = DeliveryOrder::factory()
            ->hasItems(1, [
                'product_id' => $product->id,
                'quantity' => 10,
            ])
            ->create([
                'do_number' => 'DO-2025-001',
                'do_date' => now()->startOfDay(),
            ]);

        $deliveryOrder->load('items.product');

        // Mock journal entry creation
        $mockJournalEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) use ($deliveryOrder) {
                $expectedDate = ($deliveryOrder->shipping_date ?? $deliveryOrder->do_date)->format('Y-m-d');

                return $data['entry_date'] === $expectedDate
                    && str_contains($data['description'], $deliveryOrder->do_number)
                    && $data['reference'] === $deliveryOrder->do_number
                    && $data['source_type'] === DeliveryOrder::class
                    && $data['source_id'] === $deliveryOrder->id
                    && count($data['lines']) === 2;
            }), true)
            ->andReturn($mockJournalEntry);

        $result = $this->strategy->onDeliveryShip($deliveryOrder);

        expect($result)->toBe($mockJournalEntry);
    });

    it('creates journal entry with correct account codes and amounts on delivery', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $deliveryOrder = DeliveryOrder::factory()
            ->hasItems(1, [
                'product_id' => $product->id,
                'quantity' => 5,
            ])
            ->create([
                'do_number' => 'DO-2025-002',
            ]);

        $deliveryOrder->load('items.product');

        $expectedCOGS = 500000;
        $cogsAccount = config('accounting.default_accounts.cogs', '5-1001');
        $inventoryAccount = config('accounting.default_accounts.inventory', '1-1400');

        $mockJournalEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) use ($expectedCOGS, $cogsAccount, $inventoryAccount) {
                if (count($data['lines']) !== 2) {
                    return false;
                }

                // Check COGS debit line
                $cogsLine = $data['lines'][0];
                if ($cogsLine['account_code'] !== $cogsAccount
                    || $cogsLine['debit'] !== $expectedCOGS
                    || $cogsLine['credit'] !== 0) {
                    return false;
                }

                // Check Inventory credit line
                $inventoryLine = $data['lines'][1];
                if ($inventoryLine['account_code'] !== $inventoryAccount
                    || $inventoryLine['debit'] !== 0
                    || $inventoryLine['credit'] !== $expectedCOGS) {
                    return false;
                }

                return true;
            }), true)
            ->andReturn($mockJournalEntry);

        $result = $this->strategy->onDeliveryShip($deliveryOrder);

        expect($result)->toBe($mockJournalEntry);
    });

    it('returns null when COGS is zero on delivery ship', function () {
        $serviceProduct = Product::factory()->service()->create([
            'track_inventory' => false,
            'purchase_price' => 100000,
        ]);

        $deliveryOrder = DeliveryOrder::factory()
            ->hasItems(1, [
                'product_id' => $serviceProduct->id,
                'quantity' => 5,
            ])
            ->create();

        $deliveryOrder->load('items.product');

        // Should not call journal service when COGS is zero
        $this->journalServiceMock->shouldNotReceive('createEntry');

        $result = $this->strategy->onDeliveryShip($deliveryOrder);

        expect($result)->toBeNull();
    });

    it('skips service items in delivery COGS calculation', function () {
        $inventoryProduct = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $serviceProduct = Product::factory()->service()->create([
            'track_inventory' => false,
            'purchase_price' => 200000,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create();

        $deliveryOrder->items()->createMany([
            [
                'product_id' => $inventoryProduct->id,
                'quantity' => 5,
                'unit_price' => 150000,
                'description' => 'Inventory Product',
            ],
            [
                'product_id' => $serviceProduct->id,
                'quantity' => 3,
                'unit_price' => 250000,
                'description' => 'Service Product',
            ],
        ]);

        $deliveryOrder->load('items.product');

        $expectedCOGS = 500000; // Only inventory: 100000 * 5

        $mockJournalEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) use ($expectedCOGS) {
                if (count($data['lines']) !== 2) {
                    return false;
                }

                return $data['lines'][0]['debit'] === $expectedCOGS
                    && $data['lines'][1]['credit'] === $expectedCOGS;
            }), true)
            ->andReturn($mockJournalEntry);

        $result = $this->strategy->onDeliveryShip($deliveryOrder);

        expect($result)->toBe($mockJournalEntry);
    });

    it('delegates COGS calculation to invoice strategy', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product)->create([
            'quantity' => 5,
        ]);

        $invoice->load('items.product');

        // Should use same calculation as COGSOnInvoiceStrategy
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(500000);
    });

    it('correctly handles multiple inventory items via delegated calculation', function () {
        $product1 = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 150000,
        ]);

        $product2 = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 75000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product1)->create([
            'quantity' => 3,
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product2)->create([
            'quantity' => 8,
        ]);

        $invoice->load('items.product');

        // (150000 * 3) + (75000 * 8) = 450000 + 600000 = 1050000
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(1050000);
    });
});

describe('ManualCOGSStrategy', function () {

    beforeEach(function () {
        $this->strategy = new ManualCOGSStrategy;
    });

    it('returns identifier "manual"', function () {
        expect($this->strategy->getIdentifier())->toBe('manual');
    });

    it('returns null on invoice post', function () {
        $invoice = Invoice::factory()->create();

        $result = $this->strategy->onInvoicePost($invoice);

        expect($result)->toBeNull();
    });

    it('returns null on delivery ship', function () {
        $deliveryOrder = Mockery::mock(DeliveryOrder::class);

        $result = $this->strategy->onDeliveryShip($deliveryOrder);

        expect($result)->toBeNull();
    });

    it('returns zero for COGS calculation', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product)->create([
            'quantity' => 5,
        ]);

        $invoice->load('items.product');

        // Should always return 0 for manual strategy
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(0);
    });

    it('returns zero even with multiple inventory items', function () {
        $product1 = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        $product2 = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 50000,
        ]);

        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product1)->create([
            'quantity' => 5,
        ]);

        InvoiceItem::factory()->forInvoice($invoice)->forProduct($product2)->create([
            'quantity' => 10,
        ]);

        $invoice->load('items.product');

        // Manual strategy always returns 0
        $cogs = $this->strategy->calculateCOGS($invoice);

        expect($cogs)->toBe(0);
    });
});
