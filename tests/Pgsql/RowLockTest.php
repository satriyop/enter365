<?php

declare(strict_types=1);

use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSession;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Sales\Invoice;
use Tests\Support\PostgresRowLock;

describe('PostgreSQL SELECT FOR UPDATE', function () {
    it('blocks a second session on product_stocks', function () {
        $product = Product::factory()->create(['track_inventory' => true]);
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        PostgresRowLock::assertForUpdateBlocks('product_stocks', (int) $stock->id);
    });

    it('blocks a second session on invoices', function () {
        $invoice = Invoice::factory()->sent()->create();

        PostgresRowLock::assertForUpdateBlocks('invoices', (int) $invoice->id);
    });

    it('blocks a second session on pos_sessions', function () {
        $session = PosSession::factory()->create();

        PostgresRowLock::assertForUpdateBlocks('pos_sessions', (int) $session->id);
    });

    it('blocks a second session on goods_receipt_notes', function () {
        $grn = GoodsReceiptNote::factory()->create();

        PostgresRowLock::assertForUpdateBlocks('goods_receipt_notes', (int) $grn->id);
    });

    it('blocks a second session on journal_entries', function () {
        $entry = JournalEntry::factory()->create();

        PostgresRowLock::assertForUpdateBlocks('journal_entries', (int) $entry->id);
    });
});
