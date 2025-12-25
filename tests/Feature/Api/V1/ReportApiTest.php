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
