<?php

declare(strict_types=1);

/**
 * Live Kopitiam 57 journeys on the SPA + akuntansi DB.
 *
 * Feature tests cover the API matrix. This file is the till Selesai lock:
 * a 500 on checkout (stale JE number, etc.) fails these tests.
 */
beforeEach(fn () => skipUnlessLiveFeature('pos'));

if (! function_exists('liveKopitiamUserId')) {
    function liveKopitiamUserId(string $email): int
    {
        return (int) realDb()->table('users')->where('email', $email)->value('id');
    }

    function liveKopitiamLatestSaleId(): int
    {
        return (int) (realDb()->table('pos_sales')->max('id') ?? 0);
    }

    function liveKopitiamWaitForSale(int $afterId, int $payable): object
    {
        for ($i = 0; $i < 40; $i++) {
            $sale = realDb()->table('pos_sales')
                ->where('id', '>', $afterId)
                ->where('payable_amount', $payable)
                ->orderByDesc('id')
                ->first();
            if ($sale && $sale->journal_entry_id) {
                return $sale;
            }
            usleep(250_000);
        }

        throw new RuntimeException("Live POS sale payable {$payable} did not post a journal.");
    }

    function liveKopitiamAssertJournalsBalanced(): void
    {
        $debits = (int) realDb()->table('journal_entry_lines')->sum('debit');
        $credits = (int) realDb()->table('journal_entry_lines')->sum('credit');
        expect($debits)->toBe($credits);
    }

    function liveKopitiamAssertSalePosted(object $sale, int $subtotal, int $service, int $tax, int $payable): void
    {
        expect((int) $sale->subtotal_amount)->toBe($subtotal)
            ->and((int) $sale->service_amount)->toBe($service)
            ->and((int) $sale->tax_amount)->toBe($tax)
            ->and((int) $sale->payable_amount)->toBe($payable)
            ->and($sale->journal_entry_id)->not->toBeNull()
            ->and((string) $sale->status)->toBe('completed');

        $journal = realDb()->table('journal_entries')->where('id', $sale->journal_entry_id)->first();
        expect($journal)->not->toBeNull()
            ->and((bool) $journal->is_posted)->toBeTrue()
            ->and((string) $journal->entry_number)->toStartWith('JE-');

        $dupes = realDb()->table('journal_entries')
            ->select('entry_number')
            ->groupBy('entry_number')
            ->havingRaw('count(*) > 1')
            ->get();
        expect($dupes)->toHaveCount(0);

        liveKopitiamAssertJournalsBalanced();
    }

    function liveKopitiamEnsureShop($page, string $email)
    {
        $open = realDb()->table('pos_sessions')
            ->where('opened_by', liveKopitiamUserId($email))
            ->where('status', 'open')
            ->exists();

        if (! $open) {
            $page->click('[data-testid="kasir-start"]');
        }

        $page->assertSee('Pesanan');

        return $page;
    }

    function liveKopitiamTapSku($page, string $sku, string $name)
    {
        $page->fill('[data-testid="kasir-search"]', $name)
            ->click("[data-testid=\"kasir-sku-{$sku}\"]")
            ->assertSee($name);

        return $page;
    }

    function liveKopitiamFinishExactCash($page)
    {
        $page->click('[data-testid="kasir-pay"]')
            ->click('[data-testid="kasir-exact-cash"]')
            ->click('[data-testid="kasir-finish"]')
            ->assertSee('Pembayaran berhasil')
            ->assertDontSee('Terjadi kesalahan di server')
            ->assertDontSee('Jaringan putus');

        return $page;
    }
}

describe('Siti kasir — live till', function () {
    it('lands on /kasir and cannot stay on Products, Contacts, or Faktur', function () {
        $page = loginAndVisitAs('siti@kopitiam57.test');

        $page->assertPathIs('/kasir')
            ->assertSee('Kasir')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Products')
            ->assertDontSee('Quotations')
            ->assertDontSee('Invoices')
            ->assertNoJavascriptErrors();

        $page->navigate(spaUrl('/products'))->assertPathIs('/kasir');
        $page->navigate(spaUrl('/invoices'))->assertPathIs('/kasir');
        $page->navigate(spaUrl('/contacts'))->assertPathIs('/kasir');
    });

    it('Selesai tunai uang pas on Hakau posts 25410 and a unique journal', function () {
        $beforeId = liveKopitiamLatestSaleId();
        $page = loginAndVisitAs('siti@kopitiam57.test', '/kasir');
        liveKopitiamEnsureShop($page, 'siti@kopitiam57.test');
        liveKopitiamTapSku($page, 'KT57-HAKAU', 'Hakau');

        $page->assertSee('PBJT')
            ->assertDontSee('DPP')
            ->assertDontSee('PPN');
        $page->assertSee('Rp25.410');

        liveKopitiamFinishExactCash($page);

        $sale = liveKopitiamWaitForSale($beforeId, 25_410);
        liveKopitiamAssertSalePosted($sale, 22_000, 1_100, 2_310, 25_410);
    });

    it('Selesai tunai with kembalian then Transaksi baru', function () {
        $beforeId = liveKopitiamLatestSaleId();
        $page = loginAndVisitAs('siti@kopitiam57.test', '/kasir');
        liveKopitiamEnsureShop($page, 'siti@kopitiam57.test');
        liveKopitiamTapSku($page, 'KT57-AIR', 'Air Mineral');

        $page->click('[data-testid="kasir-pay"]')
            ->click('button >> text=Rp100.000')
            ->click('[data-testid="kasir-finish"]')
            ->assertSee('Pembayaran berhasil')
            ->assertSee('Kembalian')
            ->assertDontSee('Terjadi kesalahan di server');

        $sale = liveKopitiamWaitForSale($beforeId, 9_240);
        liveKopitiamAssertSalePosted($sale, 8_000, 400, 840, 9_240);

        $page->click('[data-testid="kasir-new-sale"]')->assertSee('Pesanan');
    });

    it('Selesai QRIS on Air Mineral posts without a 500', function () {
        $beforeId = liveKopitiamLatestSaleId();
        $page = loginAndVisitAs('siti@kopitiam57.test', '/kasir');
        liveKopitiamEnsureShop($page, 'siti@kopitiam57.test');
        liveKopitiamTapSku($page, 'KT57-AIR', 'Air Mineral');

        $page->click('[data-testid="kasir-pay"]')
            ->click('[data-testid="kasir-tab-qris"]')
            ->assertSee('Tekan Selesai hanya setelah uang benar-benar masuk')
            ->click('[data-testid="kasir-finish"]')
            ->assertSee('Pembayaran berhasil')
            ->assertDontSee('Terjadi kesalahan di server');

        liveKopitiamWaitForSale($beforeId, 9_240);
        liveKopitiamAssertJournalsBalanced();
    });

    it('refuses Selesai when cash is short', function () {
        $page = loginAndVisitAs('siti@kopitiam57.test', '/kasir');
        liveKopitiamEnsureShop($page, 'siti@kopitiam57.test');
        liveKopitiamTapSku($page, 'KT57-HAKAU', 'Hakau');

        $page->click('[data-testid="kasir-pay"]')
            ->assertSee('Uang belum cukup')
            ->assertDontSee('Pembayaran berhasil');
    });

    it('Simpan parks Hakau and Ambil restores it', function () {
        $page = loginAndVisitAs('siti@kopitiam57.test', '/kasir');
        liveKopitiamEnsureShop($page, 'siti@kopitiam57.test');
        liveKopitiamTapSku($page, 'KT57-HAKAU', 'Hakau');

        $page->click('[data-testid="kasir-hold"]')
            ->assertSee('Pesanan disimpan.')
            ->click('[data-testid="kasir-holds"]')
            ->click('[data-testid="kasir-hold-take"]')
            ->assertSee('Hakau')
            ->click('[data-testid="kasir-clear"]');
    });

    it('Keluar returns to login', function () {
        $page = loginAndVisitAs('siti@kopitiam57.test', '/kasir');
        $page->click('[data-testid="kasir-logout"]')
            ->assertPathIs('/login');
    });
});

describe('Owner — live shop', function () {
    it('lands on Dashboard with Kasir and Products', function () {
        $page = loginAndVisitAs('admin@example.com');

        $page->assertSee('Dashboard')
            ->assertSee('Kasir')
            ->assertSee('Products')
            ->assertNoJavascriptErrors();

        if (liveApiModules()['quotations'] ?? false) {
            $page->assertSee('Quotations');
        } else {
            $page->assertDontSee('Quotations')
                ->assertDontSee('Invoices')
                ->assertDontSee('Purchase Orders');
        }
    });

    it('Products shows pastry and not busbars', function () {
        $page = loginAndVisitAs('admin@example.com', '/products');

        $page->assertSee('Products')
            ->assertDontSee('AC-AMMETER')
            ->assertDontSee('busbar');
        $page->fill('input[placeholder="Search by name, SKU, barcode..."]', 'Garlic Cheese')
            ->assertSee('Salt Bread Garlic Cheese')
            ->assertSee('KT57-SB-GARLIC');
    });

    it('Selesai tunai on Hakau from the owner till posts 25410', function () {
        $beforeId = liveKopitiamLatestSaleId();
        $page = loginAndVisitAs('admin@example.com', '/kasir');
        liveKopitiamEnsureShop($page, 'admin@example.com');
        $page->assertDontSee('AC-AMMETER');
        liveKopitiamTapSku($page, 'KT57-HAKAU', 'Hakau');
        $page->assertSee('Rp25.410');
        liveKopitiamFinishExactCash($page);

        $sale = liveKopitiamWaitForSale($beforeId, 25_410);
        liveKopitiamAssertSalePosted($sale, 22_000, 1_100, 2_310, 25_410);
    });

    it('restocks Garlic Cheese from Stock Adjustment', function () {
        $garlicId = (int) realDb()->table('products')->where('sku', 'KT57-SB-GARLIC')->value('id');
        $warehouseId = (int) realDb()->table('warehouses')->where('code', 'KT57-TOKO')->value('id');
        $before = (int) realDb()->table('product_stocks')
            ->where('product_id', $garlicId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');

        $page = loginAndVisitAs('admin@example.com', '/inventory/adjust');
        $page->click('[data-testid="adjust-type-in"]')
            ->click('[data-testid="adjust-product"]')
            ->click("[data-testid=\"adjust-product-option-{$garlicId}\"]")
            ->click('[data-testid="adjust-warehouse"]')
            ->click("[data-testid=\"adjust-warehouse-option-{$warehouseId}\"]")
            ->fill('[data-testid="adjust-quantity"]', '2')
            ->fill('[data-testid="adjust-unit-cost"]', '11000')
            ->fill('[data-testid="adjust-notes"]', 'Owner restock e2e')
            ->click('[data-testid="adjust-submit"]');

        for ($i = 0; $i < 20; $i++) {
            $qty = (int) realDb()->table('product_stocks')
                ->where('product_id', $garlicId)
                ->where('warehouse_id', $warehouseId)
                ->value('quantity');
            if ($qty === $before + 2) {
                expect($qty)->toBe($before + 2);

                return;
            }
            usleep(250_000);
        }

        expect(false)->toBeTrue();
    });

    it('reads Neraca Saldo after a kasir sale', function () {
        $page = loginAndVisitAs('admin@example.com', '/reports/trial-balance');
        $page->assertSee('Neraca Saldo')->assertNoJavascriptErrors();
        liveKopitiamAssertJournalsBalanced();
    });
});

describe('Akuntan Rina — live back office', function () {
    it('sees reports and is bounced off the till', function () {
        $page = loginAndVisitAs('rina@kopitiam57.test');

        $page->assertSee('Dashboard')
            ->assertDontSee('Kasir')
            ->assertNoJavascriptErrors();

        if (! (liveApiModules()['quotations'] ?? false)) {
            $page->assertDontSee('Quotations');
        }

        $page->navigate(spaUrl('/reports/trial-balance'))
            ->assertSee('Neraca Saldo')
            ->assertNoJavascriptErrors();

        $page->navigate(spaUrl('/kasir'))
            ->assertDontSee('Pesanan')
            ->assertDontSee('Pembayaran berhasil');
        liveKopitiamAssertJournalsBalanced();
    });

    it('cannot restock from Stock Adjustment', function () {
        $before = (int) realDb()->table('product_stocks')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.sku', 'KT57-SB-GARLIC')
            ->value('quantity');

        $page = loginAndVisitAs('rina@kopitiam57.test', '/inventory/adjust');
        $page->assertDontSee('Pembayaran berhasil');

        expect((int) realDb()->table('product_stocks')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.sku', 'KT57-SB-GARLIC')
            ->value('quantity'))->toBe($before);
    });
});

describe('Gudang Dewi — live inventory', function () {
    it('opens products and inventory, never the till', function () {
        $page = loginAndVisitAs('dewi@kopitiam57.test', '/products');

        $page->assertSee('Products')
            ->assertDontSee('Kasir')
            ->assertDontSee('Quotations')
            ->assertNoJavascriptErrors();

        $page->navigate(spaUrl('/inventory'))
            ->assertSee('Inventory')
            ->assertNoJavascriptErrors();

        $page->navigate(spaUrl('/kasir'))
            ->assertDontSee('Pesanan')
            ->assertDontSee('Pembayaran berhasil');
    });

    it('restocks Garlic Cheese', function () {
        $garlicId = (int) realDb()->table('products')->where('sku', 'KT57-SB-GARLIC')->value('id');
        $warehouseId = (int) realDb()->table('warehouses')->where('code', 'KT57-TOKO')->value('id');
        $before = (int) realDb()->table('product_stocks')
            ->where('product_id', $garlicId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');

        $page = loginAndVisitAs('dewi@kopitiam57.test', '/inventory/adjust');
        $page->click('[data-testid="adjust-type-in"]')
            ->click('[data-testid="adjust-product"]')
            ->click("[data-testid=\"adjust-product-option-{$garlicId}\"]")
            ->click('[data-testid="adjust-warehouse"]')
            ->click("[data-testid=\"adjust-warehouse-option-{$warehouseId}\"]")
            ->fill('[data-testid="adjust-quantity"]', '3')
            ->fill('[data-testid="adjust-unit-cost"]', '11000')
            ->fill('[data-testid="adjust-notes"]', 'Dewi restock e2e')
            ->click('[data-testid="adjust-submit"]');

        for ($i = 0; $i < 20; $i++) {
            $qty = (int) realDb()->table('product_stocks')
                ->where('product_id', $garlicId)
                ->where('warehouse_id', $warehouseId)
                ->value('quantity');
            if ($qty === $before + 3) {
                expect($qty)->toBe($before + 3);

                return;
            }
            usleep(250_000);
        }

        expect(false)->toBeTrue();
    });

    it('can open Stock Opname but the till stays closed', function () {
        $page = loginAndVisitAs('dewi@kopitiam57.test', '/inventory/opnames');
        $page->assertSee('Stock Opname')
            ->assertDontSee('Kasir')
            ->assertNoJavascriptErrors();
    });
});
