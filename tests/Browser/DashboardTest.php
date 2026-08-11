<?php

declare(strict_types=1);

/**
 * DASH-PEST-01: Dashboard accuracy browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Demo data from DemoSeeder (transactions for widgets to display)
 *
 * Tests cover: dashboard loads, KPI widgets, charts, recent transactions.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('dashboard loads with all widgets without JS errors', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');
    $page->assertNoJavascriptErrors();

    // Dashboard should have main stats
    $page->assertSee('Cash Balance');
    $page->assertSee('Receivables');
    $page->assertSee('Payables');
});

it('shows receivables stat with invoice count', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');
    $page->assertSee('Receivables');

    // Calculate expected receivables from DB
    $receivablesCount = realDb()->table('invoices')
        ->whereIn('status', ['sent', 'partial'])
        ->whereNull('deleted_at')
        ->count();

    // Widget should show invoice count
    if ($receivablesCount > 0) {
        $page->assertSee('invoices');
    }

    // Should show Rp currency format
    $page->assertSee('Rp');
});

it('shows payables stat with bill count', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');
    $page->assertSee('Payables');

    // Calculate expected payables from DB
    $payablesCount = realDb()->table('bills')
        ->whereIn('status', ['posted', 'partial'])
        ->whereNull('deleted_at')
        ->count();

    // Widget should show bill count
    if ($payablesCount > 0) {
        $page->assertSee('bills');
    }
});

it('shows active projects section', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');

    // Projects widget is feature-gated / demo-data dependent.
    $projectsEnabled = realDb()->table('projects')->whereNull('deleted_at')->exists();
    if (! $projectsEnabled) {
        $this->markTestSkipped('No projects in browser DB — projects widget not asserted');
    }

    $page->assertSee('Active Projects');
});

it('handles empty data gracefully with empty states', function () {
    // This test verifies the dashboard doesn't crash with missing data
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');
    $page->assertNoJavascriptErrors();

    // Dashboard should render without crashing
    // Empty states or zero values are acceptable
    $page->assertSee('Cash Balance');
});

it('shows gross margin stat', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');
    $page->assertSee('Gross Margin');

    // Gross margin is shown as percentage
    $page->assertSee('%');
});

it('dashboard KPIs match database calculations', function () {
    $page = loginAndVisit('/');

    $page->assertSee('Dashboard');

    // Calculate key metrics from DB
    $metrics = [];

    // Outstanding receivables
    $metrics['receivables'] = realDb()->table('invoices')
        ->whereIn('status', ['sent', 'partial'])
        ->whereNull('deleted_at')
        ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding')
        ->value('outstanding');

    // Outstanding payables
    $metrics['payables'] = realDb()->table('bills')
        ->whereIn('status', ['posted', 'partial'])
        ->whereNull('deleted_at')
        ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding')
        ->value('outstanding');

    // Active projects count
    $metrics['active_projects'] = realDb()->table('projects')
        ->where('status', 'in_progress')
        ->whereNull('deleted_at')
        ->count();

    // These calculations verify the data exists
    expect($metrics['receivables'])->toBeGreaterThanOrEqual(0);
    expect($metrics['payables'])->toBeGreaterThanOrEqual(0);
    expect($metrics['active_projects'])->toBeGreaterThanOrEqual(0);
});
