<?php

declare(strict_types=1);

/**
 * Kopitiam 57 coffee-shop journeys on the live SPA.
 *
 * Runs only when the live API has the pos pack. Document-sales tests stay
 * skipped via skipUnlessLiveFeature on their own files.
 */
beforeEach(fn () => skipUnlessLiveFeature('pos'));

it('sends the owner to Dashboard without Faktur or Penawaran', function () {
    $page = loginAndVisitAs('admin@example.com');

    $page->assertSee('Dashboard')
        ->assertSee('Kasir')
        ->assertSee('Products')
        ->assertDontSee('Quotations')
        ->assertDontSee('Invoices')
        ->assertDontSee('Purchase Orders')
        ->assertNoJavascriptErrors();
});

it('sends Siti kasir to the till, not ERP chrome', function () {
    $page = loginAndVisitAs('siti@kopitiam57.test');

    $page->assertPathIs('/kasir')
        ->assertSee('Kasir')
        ->assertDontSee('Dashboard')
        ->assertDontSee('Products')
        ->assertNoJavascriptErrors();
});

it('hides the till from the akuntan and still shows reports', function () {
    $page = loginAndVisitAs('rina@kopitiam57.test');

    $page->assertSee('Dashboard')
        ->assertDontSee('Kasir')
        ->assertNoJavascriptErrors();

    $page = $page->navigate(spaUrl('/reports/trial-balance'));
    $page->assertSee('Neraca Saldo')
        ->assertNoJavascriptErrors();
});

it('lets gudang open products and inventory, not the till', function () {
    $page = loginAndVisitAs('dewi@kopitiam57.test', '/products');

    $page->assertSee('Products')
        ->assertDontSee('Kasir')
        ->assertNoJavascriptErrors();

    $page = $page->navigate(spaUrl('/inventory'));
    $page->assertSee('Inventory')
        ->assertNoJavascriptErrors();
});

it('lets the owner open the till', function () {
    $page = loginAndVisitAs('admin@example.com', '/kasir');

    $page->assertNoJavascriptErrors();
    $page->assertSee('Kasir');
});
