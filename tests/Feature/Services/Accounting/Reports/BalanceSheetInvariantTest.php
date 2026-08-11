<?php

declare(strict_types=1);

/**
 * Balance Sheet Invariant Tests
 *
 * Property-based tests that seed full demo data and verify accounting
 * invariants hold. These catch bugs that synthetic tests miss:
 * null subtypes, credit-balance expenses, landed costs, etc.
 *
 * These tests are intentionally slow (~30s) because they seed the full
 * DemoSeeder. Run separately with: php artisan test --filter=BalanceSheetInvariant
 */

use App\Services\Accounting\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Demo seeders call Auth::login(); keep the session (web) guard as default.
    // Do not use Sanctum::actingAs here — RequestGuard has no login().
    Auth::shouldUse('web');

    // Demo master data assigns roles; seed roles/permissions first.
    // DemoSeeder falls back to DEMO_ALL in non-interactive mode (tests/CI).
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\Demo\\DemoSeeder']);
});

it('balance sheet always balances with demo data', function () {
    $service = app(FinancialReportService::class);
    $bs = $service->getBalanceSheet(now()->toDateString());

    expect($bs['is_balanced'])->toBeTrue(
        sprintf(
            'Balance sheet is unbalanced: assets=%s, liabilities=%s, equity=%s, L+E=%s',
            number_format($bs['total_assets']),
            number_format($bs['total_liabilities']),
            number_format($bs['total_equity']),
            number_format($bs['total_liabilities_equity']),
        )
    );

    // The fundamental accounting equation: A = L + E
    expect($bs['total_assets'])->toBe(
        $bs['total_liabilities'] + $bs['total_equity'],
        'Assets must equal Liabilities + Equity'
    );
});

it('balance sheet balances at historical date', function () {
    $service = app(FinancialReportService::class);

    // Test at start of fiscal year — catches opening balance issues
    $startOfYear = now()->startOfYear()->toDateString();
    $bs = $service->getBalanceSheet($startOfYear);

    expect($bs['is_balanced'])->toBeTrue(
        sprintf(
            'Balance sheet unbalanced at %s: assets=%s, L+E=%s',
            $startOfYear,
            number_format($bs['total_assets']),
            number_format($bs['total_liabilities_equity']),
        )
    );
});

it('net income in balance sheet matches income statement', function () {
    $service = app(FinancialReportService::class);

    $periodStart = now()->startOfYear()->toDateString();
    $periodEnd = now()->toDateString();

    $bs = $service->getBalanceSheet($periodEnd);
    $is = $service->getIncomeStatement($periodStart, $periodEnd);

    // Find "Laba/Rugi Berjalan" (current period net income) in equity section
    $netIncomeInEquity = null;
    foreach ($bs['equity'] as $section) {
        $accounts = collect($section['accounts'] ?? []);
        $netIncomeLine = $accounts->first(function ($item) {
            // The virtual account injected by the service for current period P&L
            return str_contains($item->name ?? '', 'Laba/Rugi Berjalan')
                || str_contains($item->name ?? '', 'Laba Rugi Berjalan');
        });

        if ($netIncomeLine) {
            $netIncomeInEquity = $netIncomeLine->balance;
            break;
        }
    }

    // If there's a net income line in equity, it must match the income statement
    if ($netIncomeInEquity !== null) {
        expect($netIncomeInEquity)->toBe(
            $is['net_income'],
            sprintf(
                'Net income mismatch: balance sheet equity shows %s but income statement shows %s',
                number_format($netIncomeInEquity),
                number_format($is['net_income']),
            )
        );
    }
});

it('total assets are non-negative with demo data', function () {
    $service = app(FinancialReportService::class);
    $bs = $service->getBalanceSheet(now()->toDateString());

    // In a normally operating business, total assets should be positive
    expect($bs['total_assets'])->toBeGreaterThan(0, 'Demo data should produce positive assets');
});
