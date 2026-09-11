<?php

use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Period-end cutover reviews (#149)', function () {
    it('lists completed receipts without a posted vendor bill', function () {
        $open = GoodsReceiptNote::factory()->completed()->create([
            'receipt_date' => '2026-03-10',
        ]);
        GoodsReceiptNoteItem::factory()->create([
            'goods_receipt_note_id' => $open->id,
            'quantity_received' => 2,
            'unit_price' => 50_000,
        ]);

        $matched = GoodsReceiptNote::factory()->completed()->create([
            'receipt_date' => '2026-03-11',
        ]);
        Bill::factory()->received()->create([
            'purchase_order_id' => $matched->purchase_order_id,
            'bill_date' => '2026-03-12',
        ]);

        GoodsReceiptNote::factory()->create([
            'status' => DocumentStatus::Draft,
            'receipt_date' => '2026-03-10',
        ]);

        $response = $this->getJson('/api/v1/reports/cutover/bill-to-receive?as_of_date=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.kind', 'bill-to-receive')
            ->assertJsonPath('data.totals.count', 1);

        expect($response->json('data.rows.0.number'))->toBe($open->grn_number)
            ->and($response->json('data.totals.amount'))->toBe(100_000);
    });

    it('lists posted bills without a completed receipt', function () {
        $open = Bill::factory()->received()->create([
            'bill_date' => '2026-03-08',
            'total_amount' => 75_000,
        ]);

        $grn = GoodsReceiptNote::factory()->completed()->create([
            'receipt_date' => '2026-03-09',
        ]);
        Bill::factory()->received()->create([
            'purchase_order_id' => $grn->purchase_order_id,
            'bill_date' => '2026-03-10',
            'total_amount' => 10_000,
        ]);

        Bill::factory()->create([
            'status' => DocumentStatus::Draft,
            'bill_date' => '2026-03-08',
        ]);

        $response = $this->getJson('/api/v1/reports/cutover/billed-not-received?as_of_date=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.kind', 'billed-not-received')
            ->assertJsonPath('data.totals.count', 1);

        expect($response->json('data.rows.0.number'))->toBe($open->bill_number)
            ->and($response->json('data.totals.amount'))->toBe(75_000);
    });

    it('lists delivered orders without a posted customer invoice', function () {
        $open = DeliveryOrder::factory()->delivered()->create([
            'do_date' => '2026-03-05',
            'invoice_id' => null,
        ]);
        DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $open->id,
            'line_total' => 40_000,
        ]);

        $invoice = Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-04',
        ]);
        DeliveryOrder::factory()->delivered()->create([
            'do_date' => '2026-03-06',
            'invoice_id' => $invoice->id,
        ]);

        DeliveryOrder::factory()->create([
            'status' => DocumentStatus::Draft,
            'do_date' => '2026-03-05',
        ]);

        $response = $this->getJson('/api/v1/reports/cutover/invoices-to-be-issued?as_of_date=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.kind', 'invoices-to-be-issued')
            ->assertJsonPath('data.totals.count', 1);

        expect($response->json('data.rows.0.number'))->toBe($open->do_number)
            ->and($response->json('data.totals.amount'))->toBe(40_000);
    });

    it('lists posted invoices without a shipped delivery', function () {
        $open = Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-02',
            'total_amount' => 88_000,
        ]);

        $matched = Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-03',
            'total_amount' => 12_000,
        ]);
        DeliveryOrder::factory()->shipped()->create([
            'do_date' => '2026-03-04',
            'invoice_id' => $matched->id,
        ]);

        Invoice::factory()->create([
            'status' => DocumentStatus::Draft,
            'invoice_date' => '2026-03-02',
        ]);

        $response = $this->getJson('/api/v1/reports/cutover/invoiced-not-delivered?as_of_date=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.kind', 'invoiced-not-delivered')
            ->assertJsonPath('data.totals.count', 1);

        expect($response->json('data.rows.0.number'))->toBe($open->invoice_number)
            ->and($response->json('data.totals.amount'))->toBe(88_000);
    });

    it('respects as_of_date cut-off', function () {
        GoodsReceiptNote::factory()->completed()->create([
            'receipt_date' => '2026-04-01',
        ]);

        $this->getJson('/api/v1/reports/cutover/bill-to-receive?as_of_date=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.totals.count', 0);
    });
});
