<?php

declare(strict_types=1);

/**
 * SMOKE-PEST-01: Smoke tests for all SPA pages.
 *
 * Visits every major page to ensure they load without JS errors.
 * Does NOT test functionality — just that pages render.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Frontend dev server running at SPA_URL
 */

// ---------------------------------------------------------------------------
// Smoke Test: Pages load without JS errors
// ---------------------------------------------------------------------------

it('loads the SPA login page', function () {
    $page = visit(spaUrl('/login'));

    $page->assertSee('Sign in to your account');
});

it('has no javascript errors on login page', function () {
    $page = visit(spaUrl('/login'));

    $page->assertNoJavascriptErrors();
});

it('loads dashboard without JS errors', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');
    $page->assertNoJavascriptErrors();
});

// --- Master Data pages ---

it('loads contacts list without JS errors', function () {
    $page = loginAndVisit('/contacts');

    $page->assertSee('Contacts');
    $page->assertNoJavascriptErrors();
});

it('loads products list without JS errors', function () {
    $page = loginAndVisit('/products');

    $page->assertSee('Products');
    $page->assertNoJavascriptErrors();
});

it('loads warehouses list without JS errors', function () {
    $page = loginAndVisit('/settings/warehouses');

    $page->assertSee('Warehouses');
    $page->assertNoJavascriptErrors();
});

// --- Sales pages ---

it('loads quotations list without JS errors', function () {
    $page = loginAndVisit('/quotations');

    $page->assertSee('Quotations');
    $page->assertNoJavascriptErrors();
});

it('loads invoices list without JS errors', function () {
    $page = loginAndVisit('/invoices');

    $page->assertSee('Invoices');
    $page->assertNoJavascriptErrors();
});

it('loads delivery orders list without JS errors', function () {
    $page = loginAndVisit('/sales/delivery-orders');

    $page->assertSee('Delivery Orders');
    $page->assertNoJavascriptErrors();
});

it('loads sales returns list without JS errors', function () {
    $page = loginAndVisit('/sales/sales-returns');

    $page->assertSee('Sales Returns');
    $page->assertNoJavascriptErrors();
});

// --- Purchasing pages ---

it('loads purchase orders list without JS errors', function () {
    $page = loginAndVisit('/purchasing/purchase-orders');

    $page->assertSee('Purchase Orders');
    $page->assertNoJavascriptErrors();
});

it('loads goods receipt notes list without JS errors', function () {
    $page = loginAndVisit('/purchasing/goods-receipt-notes');

    $page->assertSee('Goods Receipt Notes');
    $page->assertNoJavascriptErrors();
});

it('loads bills list without JS errors', function () {
    $page = loginAndVisit('/bills');

    $page->assertSee('Bills');
    $page->assertNoJavascriptErrors();
});

it('loads purchase returns list without JS errors', function () {
    $page = loginAndVisit('/purchasing/purchase-returns');

    $page->assertSee('Purchase Returns');
    $page->assertNoJavascriptErrors();
});

// --- Inventory pages ---

it('loads inventory list without JS errors', function () {
    $page = loginAndVisit('/inventory');

    $page->assertSee('Inventory');
    $page->assertNoJavascriptErrors();
});

it('loads stock opname list without JS errors', function () {
    $page = loginAndVisit('/inventory/opnames');

    $page->assertSee('Stock Opname');
    $page->assertNoJavascriptErrors();
});

it('loads stock movements page without JS errors', function () {
    $page = loginAndVisit('/inventory/movements');

    $page->assertSee('Stock Movements');
    $page->assertNoJavascriptErrors();
});

it('loads stock adjustment page without JS errors', function () {
    $page = loginAndVisit('/inventory/adjust');

    $page->assertSee('Stock Adjustment');
    $page->assertNoJavascriptErrors();
});

it('loads stock transfer page without JS errors', function () {
    $page = loginAndVisit('/inventory/transfer');

    $page->assertSee('Stock Transfer');
    $page->assertNoJavascriptErrors();
});

it('loads movement summary page without JS errors', function () {
    $page = loginAndVisit('/inventory/movement-summary');

    $page->assertSee('Movement Summary');
    $page->assertNoJavascriptErrors();
});

// --- Accounting pages ---

it('loads chart of accounts without JS errors', function () {
    $page = loginAndVisit('/accounting/accounts');

    $page->assertSee('Chart of Accounts');
    $page->assertNoJavascriptErrors();
});

it('loads journal entries list without JS errors', function () {
    $page = loginAndVisit('/accounting/journal-entries');

    $page->assertSee('Journal Entries');
    $page->assertNoJavascriptErrors();
});

it('loads fiscal periods list without JS errors', function () {
    $page = loginAndVisit('/accounting/fiscal-periods');

    $page->assertSee('Fiscal Periods');
    $page->assertNoJavascriptErrors();
});

it('loads payments list without JS errors', function () {
    $page = loginAndVisit('/payments');

    $page->assertSee('Payments');
    $page->assertNoJavascriptErrors();
});

it('loads down payments list without JS errors', function () {
    $page = loginAndVisit('/finance/down-payments');

    $page->assertSee('Down Payments');
    $page->assertNoJavascriptErrors();
});

// --- Report pages ---

it('loads reports index without JS errors', function () {
    $page = loginAndVisit('/reports');

    $page->assertSee('Reports');
    $page->assertNoJavascriptErrors();
});

it('loads cash flow report without JS errors', function () {
    $page = loginAndVisit('/reports/cash-flow');

    $page->assertSee('Cash Flow');
    $page->assertNoJavascriptErrors();
});

it('loads receivables aging report without JS errors', function () {
    $page = loginAndVisit('/reports/receivables-aging');

    $page->assertSee('Receivables');
    $page->assertNoJavascriptErrors();
});

it('loads payables aging report without JS errors', function () {
    $page = loginAndVisit('/reports/payables-aging');

    $page->assertSee('Payables');
    $page->assertNoJavascriptErrors();
});

it('loads VAT report without JS errors', function () {
    $page = loginAndVisit('/reports/vat');

    $page->assertSee('VAT');
    $page->assertNoJavascriptErrors();
});

it('loads general ledger report without JS errors', function () {
    $page = loginAndVisit('/reports/general-ledger');

    $page->assertSee('General Ledger');
    $page->assertNoJavascriptErrors();
});

it('loads stock summary report without JS errors', function () {
    $page = loginAndVisit('/reports/stock-summary');

    $page->assertSee('Stock');
    $page->assertNoJavascriptErrors();
});

it('loads stock valuation report without JS errors', function () {
    $page = loginAndVisit('/reports/stock-valuation');

    $page->assertSee('Valuation');
    $page->assertNoJavascriptErrors();
});

it('loads COGS summary report without JS errors', function () {
    $page = loginAndVisit('/reports/cogs-summary');

    $page->assertSee('COGS');
    $page->assertNoJavascriptErrors();
});

it('loads changes in equity report without JS errors', function () {
    $page = loginAndVisit('/reports/changes-in-equity');

    $page->assertSee('Equity');
    $page->assertNoJavascriptErrors();
});
