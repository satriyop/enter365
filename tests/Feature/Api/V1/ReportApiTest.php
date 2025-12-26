<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Report API', function () {

    it('can generate trial balance', function () {
        // Create some journal entries to have balances
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($entry)->forAccount($cashAccount)->debit(1000000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($revenueAccount)->credit(1000000)->create();

        $response = $this->getJson('/api/v1/reports/trial-balance');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'as_of_date',
                'accounts' => [
                    '*' => ['account_id', 'code', 'name', 'type', 'debit_balance', 'credit_balance'],
                ],
                'total_debit',
                'total_credit',
                'is_balanced',
            ])
            ->assertJsonPath('report_name', 'Neraca Saldo')
            ->assertJsonPath('is_balanced', true);
    });

    it('can generate trial balance for specific date', function () {
        $response = $this->getJson('/api/v1/reports/trial-balance?as_of_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('as_of_date', '2024-12-31');
    });

    it('can generate balance sheet', function () {
        $response = $this->getJson('/api/v1/reports/balance-sheet');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'as_of_date',
                'assets' => ['current', 'fixed', 'total'],
                'liabilities' => ['current', 'long_term', 'total'],
                'equity' => ['items', 'total'],
                'total_liabilities_equity',
            ])
            ->assertJsonPath('report_name', 'Laporan Posisi Keuangan');
    });

    it('can generate income statement', function () {
        // Create revenue and expense entries
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();
        $expenseAccount = Account::where('code', '5-2001')->first();

        // Revenue entry
        $entry1 = JournalEntry::factory()->posted()->create(['entry_date' => now()->toDateString()]);
        JournalEntryLine::factory()->forEntry($entry1)->forAccount($cashAccount)->debit(5000000)->create();
        JournalEntryLine::factory()->forEntry($entry1)->forAccount($revenueAccount)->credit(5000000)->create();

        // Expense entry
        $entry2 = JournalEntry::factory()->posted()->create(['entry_date' => now()->toDateString()]);
        JournalEntryLine::factory()->forEntry($entry2)->forAccount($expenseAccount)->debit(2000000)->create();
        JournalEntryLine::factory()->forEntry($entry2)->forAccount($cashAccount)->credit(2000000)->create();

        $response = $this->getJson('/api/v1/reports/income-statement');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period_start',
                'period_end',
                'revenue' => ['operating', 'other', 'total'],
                'expenses' => ['cost_of_goods', 'operating', 'other', 'total'],
                'gross_profit',
                'operating_income',
                'net_income',
            ])
            ->assertJsonPath('report_name', 'Laporan Laba Rugi');

        // Verify net income is calculated (revenue - expenses)
        $netIncome = $response->json('net_income');
        expect($netIncome)->toBeGreaterThanOrEqual(0);
    });

    it('can generate income statement for date range', function () {
        $response = $this->getJson('/api/v1/reports/income-statement?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('period_start', '2024-01-01')
            ->assertJsonPath('period_end', '2024-12-31');
    });

    it('can generate general ledger', function () {
        // Create some transactions
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->posted()->create(['entry_date' => now()->toDateString()]);
        JournalEntryLine::factory()->forEntry($entry)->forAccount($cashAccount)->debit(1000000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($revenueAccount)->credit(1000000)->create();

        $response = $this->getJson('/api/v1/reports/general-ledger');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'start_date',
                'end_date',
                'accounts' => [
                    '*' => [
                        'account_id',
                        'code',
                        'name',
                        'type',
                        'opening_balance',
                        'entries',
                        'closing_balance',
                    ],
                ],
            ])
            ->assertJsonPath('report_name', 'Buku Besar');
    });

    it('can generate general ledger for date range', function () {
        $response = $this->getJson('/api/v1/reports/general-ledger?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('start_date', '2024-01-01')
            ->assertJsonPath('end_date', '2024-12-31');
    });
});

describe('Comparative Reports', function () {

    it('can generate comparative balance sheet', function () {
        // Create transactions for current period
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($entry)->forAccount($cashAccount)->debit(10000000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($revenueAccount)->credit(10000000)->create();

        // Create transactions for previous period
        $previousEntry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->subYear()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($previousEntry)->forAccount($cashAccount)->debit(5000000)->create();
        JournalEntryLine::factory()->forEntry($previousEntry)->forAccount($revenueAccount)->credit(5000000)->create();

        $currentDate = now()->toDateString();
        $previousDate = now()->subYear()->toDateString();

        $response = $this->getJson("/api/v1/reports/balance-sheet?as_of_date={$currentDate}&compare_to={$previousDate}");

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'current_period' => [
                    'as_of_date',
                    'assets' => ['current', 'fixed', 'total'],
                    'liabilities' => ['current', 'long_term', 'total'],
                    'equity' => ['items', 'total'],
                ],
                'previous_period' => [
                    'as_of_date',
                    'assets',
                    'liabilities',
                    'equity',
                ],
                'variance' => [
                    'assets_change',
                    'assets_change_percent',
                    'liabilities_change',
                    'liabilities_change_percent',
                    'equity_change',
                    'equity_change_percent',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Posisi Keuangan Komparatif');
    });

    it('calculates balance sheet variance correctly', function () {
        // First, create base data with the regular balance sheet
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($entry)->forAccount($cashAccount)->debit(10000000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($revenueAccount)->credit(10000000)->create();

        // Verify data exists via regular balance sheet
        $regularResponse = $this->getJson('/api/v1/reports/balance-sheet');
        $regularResponse->assertOk();

        // Now test comparative - since we have data, assets should exist
        $currentDate = now()->toDateString();
        $previousDate = now()->subYear()->toDateString();

        $response = $this->getJson("/api/v1/reports/balance-sheet?as_of_date={$currentDate}&compare_to={$previousDate}");

        $response->assertOk();

        $currentAssets = $response->json('current_period.assets.total');
        $previousAssets = $response->json('previous_period.assets.total');
        $variance = $response->json('variance');

        // Current should have assets from the entry, previous should have less/none
        expect($currentAssets)->toBeGreaterThanOrEqual($previousAssets);
        expect($variance['assets_change'])->toBe($currentAssets - $previousAssets);
        expect($variance)->toHaveKeys(['assets_change', 'assets_change_percent', 'liabilities_change', 'equity_change']);
    });

    it('can generate comparative income statement', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        // Current period revenue
        $currentEntry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($currentEntry)->forAccount($cashAccount)->debit(20000000)->create();
        JournalEntryLine::factory()->forEntry($currentEntry)->forAccount($revenueAccount)->credit(20000000)->create();

        // Previous period revenue
        $previousEntry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->subYear()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($previousEntry)->forAccount($cashAccount)->debit(10000000)->create();
        JournalEntryLine::factory()->forEntry($previousEntry)->forAccount($revenueAccount)->credit(10000000)->create();

        $response = $this->getJson('/api/v1/reports/income-statement?compare_previous_period=true');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'current_period' => [
                    'period_start',
                    'period_end',
                    'revenue' => ['operating', 'other', 'total'],
                    'expenses',
                    'net_income',
                ],
                'previous_period' => [
                    'period_start',
                    'period_end',
                    'revenue',
                    'expenses',
                    'net_income',
                ],
                'variance' => [
                    'revenue_change',
                    'revenue_change_percent',
                    'expenses_change',
                    'expenses_change_percent',
                    'net_income_change',
                    'net_income_change_percent',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Laba Rugi Komparatif');
    });

    it('calculates income statement variance correctly', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        // Previous year: 10M revenue
        $previousEntry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->subYear()->startOfYear()->addMonth()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($previousEntry)->forAccount($cashAccount)->debit(10000000)->create();
        JournalEntryLine::factory()->forEntry($previousEntry)->forAccount($revenueAccount)->credit(10000000)->create();

        // Current year: 25M revenue
        $currentEntry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->startOfYear()->addMonth()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($currentEntry)->forAccount($cashAccount)->debit(25000000)->create();
        JournalEntryLine::factory()->forEntry($currentEntry)->forAccount($revenueAccount)->credit(25000000)->create();

        $response = $this->getJson('/api/v1/reports/income-statement?compare_previous_period=true');

        $response->assertOk();

        $variance = $response->json('variance');
        // Revenue increased by 15M (from 10M to 25M) = 150% increase
        expect($variance['revenue_change'])->toBe(15000000);
        expect((float) $variance['revenue_change_percent'])->toBe(150.0);
    });

    it('can specify custom previous period for income statement', function () {
        $response = $this->getJson('/api/v1/reports/income-statement?start_date=2024-01-01&end_date=2024-06-30&compare_previous_period=true&previous_start_date=2023-01-01&previous_end_date=2023-06-30');

        $response->assertOk()
            ->assertJsonPath('current_period.period_start', '2024-01-01')
            ->assertJsonPath('current_period.period_end', '2024-06-30')
            ->assertJsonPath('previous_period.period_start', '2023-01-01')
            ->assertJsonPath('previous_period.period_end', '2023-06-30');
    });
});
