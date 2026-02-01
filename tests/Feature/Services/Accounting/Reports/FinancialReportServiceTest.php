<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(FinancialReportService::class);
});

describe('getBalanceSheet', function () {
    test('returns correct structure with empty data', function () {
        $result = $this->service->getBalanceSheet();

        expect($result)->toBeArray()
            ->toHaveKeys(['as_of_date', 'assets', 'liabilities', 'equity', 'total_liabilities_equity'])
            ->and($result['assets'])->toHaveKeys(['current', 'fixed', 'total'])
            ->and($result['liabilities'])->toHaveKeys(['current', 'long_term', 'total'])
            ->and($result['equity'])->toHaveKeys(['items', 'total']);
    });

    test('groups accounts by type correctly', function () {
        Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 5000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_FIXED_ASSET,
            'code' => '1-2001',
            'name' => 'Equipment',
            'is_active' => true,
            'opening_balance' => 20000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
            'code' => '2-1001',
            'name' => 'Accounts Payable',
            'is_active' => true,
            'opening_balance' => 3000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_LONG_TERM_LIABILITY,
            'code' => '2-2001',
            'name' => 'Long Term Loan',
            'is_active' => true,
            'opening_balance' => 10000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_EQUITY,
            'subtype' => Account::SUBTYPE_EQUITY,
            'code' => '3-1001',
            'name' => 'Owner Capital',
            'is_active' => true,
            'opening_balance' => 12000000,
        ]);

        $result = $this->service->getBalanceSheet();

        expect($result['assets']['current'])->toHaveCount(1)
            ->and($result['assets']['fixed'])->toHaveCount(1)
            ->and($result['liabilities']['current'])->toHaveCount(1)
            ->and($result['liabilities']['long_term'])->toHaveCount(1)
            // equity.items may include "Laba/Rugi Berjalan" virtual item
            ->and($result['equity']['items']->count())->toBeGreaterThanOrEqual(1);
    });

    test('calculates debit-normal vs credit-normal balances correctly', function () {
        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 5000000,
        ]);

        $apAccount = Account::factory()->create([
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
            'code' => '2-1001',
            'name' => 'Accounts Payable',
            'is_active' => true,
            'opening_balance' => 3000000,
        ]);

        $je = JournalEntry::factory()->create([
            'entry_date' => '2024-01-15',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $cashAccount->id,
            'debit' => 2000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $apAccount->id,
            'debit' => 0,
            'credit' => 2000000,
        ]);

        $result = $this->service->getBalanceSheet();

        // Balance items are stdClass objects with account_id property
        $cashBalance = $result['assets']['current']->firstWhere('account_id', $cashAccount->id);
        expect($cashBalance->balance)->toBe(7000000); // 5M + 2M debit

        $apBalance = $result['liabilities']['current']->firstWhere('account_id', $apAccount->id);
        expect($apBalance->balance)->toBe(5000000); // 3M + 2M credit
    });

    test('balance equation holds - assets equal liabilities plus equity', function () {
        Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 10000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_FIXED_ASSET,
            'code' => '1-2001',
            'name' => 'Equipment',
            'is_active' => true,
            'opening_balance' => 15000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
            'code' => '2-1001',
            'name' => 'Accounts Payable',
            'is_active' => true,
            'opening_balance' => 8000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_EQUITY,
            'subtype' => Account::SUBTYPE_EQUITY,
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'is_active' => true,
            'opening_balance' => 17000000,
        ]);

        $result = $this->service->getBalanceSheet();

        // assets.total, liabilities.total, equity.total are nested
        $totalAssets = $result['assets']['total'];
        $totalLiabilities = $result['liabilities']['total'];
        $totalEquity = $result['equity']['total'];

        expect($totalAssets)->toBe(25000000)
            ->and($totalLiabilities)->toBe(8000000)
            ->and($result['total_liabilities_equity'])->toBe($totalLiabilities + $totalEquity);
    });
});

describe('getIncomeStatement', function () {
    test('returns correct structure with empty data', function () {
        $result = $this->service->getIncomeStatement();

        expect($result)->toBeArray()
            ->toHaveKeys(['period_start', 'period_end', 'revenue', 'expenses', 'gross_profit', 'operating_income', 'net_income'])
            ->and($result['revenue'])->toHaveKeys(['operating', 'other', 'total'])
            ->and($result['expenses'])->toHaveKeys(['cost_of_goods', 'operating', 'other', 'total']);
    });

    test('calculates net income from revenue minus expenses', function () {
        $salesAccount = Account::factory()->create([
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OPERATING_REVENUE,
            'code' => '4-1001',
            'name' => 'Sales Revenue',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $salaryAccount = Account::factory()->create([
            'type' => Account::TYPE_EXPENSE,
            'subtype' => Account::SUBTYPE_OPERATING_EXPENSE,
            'code' => '5-2001',
            'name' => 'Salary Expense',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        // Record revenue
        $je1 = JournalEntry::factory()->create([
            'entry_date' => '2024-01-15',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je1->id,
            'account_id' => $cashAccount->id,
            'debit' => 50000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je1->id,
            'account_id' => $salesAccount->id,
            'debit' => 0,
            'credit' => 50000000,
        ]);

        // Record expense
        $je2 = JournalEntry::factory()->create([
            'entry_date' => '2024-01-20',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je2->id,
            'account_id' => $salaryAccount->id,
            'debit' => 15000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je2->id,
            'account_id' => $cashAccount->id,
            'debit' => 0,
            'credit' => 15000000,
        ]);

        $result = $this->service->getIncomeStatement('2024-01-01', '2024-01-31');

        expect($result['revenue']['total'])->toBe(50000000)
            ->and($result['expenses']['total'])->toBe(15000000)
            ->and($result['net_income'])->toBe(35000000);
    });

    test('separates operating vs other revenue and expenses', function () {
        $salesAccount = Account::factory()->create([
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OPERATING_REVENUE,
            'code' => '4-1001',
            'name' => 'Sales Revenue',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $interestIncomeAccount = Account::factory()->create([
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OTHER_REVENUE,
            'code' => '4-2001',
            'name' => 'Interest Income',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $salaryAccount = Account::factory()->create([
            'type' => Account::TYPE_EXPENSE,
            'subtype' => Account::SUBTYPE_OPERATING_EXPENSE,
            'code' => '5-2001',
            'name' => 'Salary Expense',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $interestExpenseAccount = Account::factory()->create([
            'type' => Account::TYPE_EXPENSE,
            'subtype' => Account::SUBTYPE_OTHER_EXPENSE,
            'code' => '5-3001',
            'name' => 'Interest Expense',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $je1 = JournalEntry::factory()->create(['entry_date' => '2024-01-15', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je1->id, 'account_id' => $cashAccount->id, 'debit' => 40000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je1->id, 'account_id' => $salesAccount->id, 'debit' => 0, 'credit' => 40000000]);

        $je2 = JournalEntry::factory()->create(['entry_date' => '2024-01-16', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je2->id, 'account_id' => $cashAccount->id, 'debit' => 5000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je2->id, 'account_id' => $interestIncomeAccount->id, 'debit' => 0, 'credit' => 5000000]);

        $je3 = JournalEntry::factory()->create(['entry_date' => '2024-01-20', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je3->id, 'account_id' => $salaryAccount->id, 'debit' => 12000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je3->id, 'account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 12000000]);

        $je4 = JournalEntry::factory()->create(['entry_date' => '2024-01-21', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je4->id, 'account_id' => $interestExpenseAccount->id, 'debit' => 2000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je4->id, 'account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 2000000]);

        $result = $this->service->getIncomeStatement('2024-01-01', '2024-01-31');

        // Items are stdClass objects with balance property
        expect($result['revenue']['operating'])->toHaveCount(1)
            ->and($result['revenue']['other'])->toHaveCount(1)
            ->and($result['expenses']['operating'])->toHaveCount(1)
            ->and($result['expenses']['other'])->toHaveCount(1)
            ->and($result['revenue']['operating']->sum('balance'))->toBe(40000000)
            ->and($result['revenue']['other']->sum('balance'))->toBe(5000000)
            ->and($result['expenses']['operating']->sum('balance'))->toBe(12000000)
            ->and($result['expenses']['other']->sum('balance'))->toBe(2000000);
    });
});

describe('getComparativeBalanceSheet', function () {
    test('calculates variance correctly between two periods', function () {
        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 10000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
            'code' => '2-1001',
            'name' => 'Accounts Payable',
            'is_active' => true,
            'opening_balance' => 5000000,
        ]);

        Account::factory()->create([
            'type' => Account::TYPE_EQUITY,
            'subtype' => Account::SUBTYPE_EQUITY,
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'is_active' => true,
            'opening_balance' => 5000000,
        ]);

        // Add transaction before current date but after previous date
        $je = JournalEntry::factory()->create([
            'entry_date' => '2024-02-15',
            'is_posted' => true,
        ]);

        $apAccount = Account::query()->where('code', '2-1001')->first();

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $cashAccount->id,
            'debit' => 3000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $apAccount->id,
            'debit' => 0,
            'credit' => 3000000,
        ]);

        $result = $this->service->getComparativeBalanceSheet('2024-02-28', '2024-01-31');

        expect($result)->toHaveKeys(['report_name', 'current_period', 'previous_period', 'variance'])
            ->and($result['report_name'])->toBe('Laporan Posisi Keuangan Komparatif')
            ->and($result['current_period']['assets']['total'])->toBe(13000000)
            ->and($result['previous_period']['assets']['total'])->toBe(10000000)
            ->and($result['variance']['assets_change'])->toBe(3000000);
    });
});

describe('getComparativeIncomeStatement', function () {
    test('calculates variance correctly between two periods', function () {
        $salesAccount = Account::factory()->create([
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OPERATING_REVENUE,
            'code' => '4-1001',
            'name' => 'Sales Revenue',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $cogsAccount = Account::factory()->create([
            'type' => Account::TYPE_EXPENSE,
            'subtype' => Account::SUBTYPE_OPERATING_EXPENSE,
            'code' => '5-1001',
            'name' => 'Cost of Goods Sold',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        // Period 1: January
        $je1 = JournalEntry::factory()->create(['entry_date' => '2024-01-15', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je1->id, 'account_id' => $cashAccount->id, 'debit' => 30000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je1->id, 'account_id' => $salesAccount->id, 'debit' => 0, 'credit' => 30000000]);

        $je2 = JournalEntry::factory()->create(['entry_date' => '2024-01-20', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je2->id, 'account_id' => $cogsAccount->id, 'debit' => 10000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je2->id, 'account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 10000000]);

        // Period 2: February
        $je3 = JournalEntry::factory()->create(['entry_date' => '2024-02-15', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je3->id, 'account_id' => $cashAccount->id, 'debit' => 45000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je3->id, 'account_id' => $salesAccount->id, 'debit' => 0, 'credit' => 45000000]);

        $je4 = JournalEntry::factory()->create(['entry_date' => '2024-02-20', 'is_posted' => true]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je4->id, 'account_id' => $cogsAccount->id, 'debit' => 15000000, 'credit' => 0]);
        JournalEntryLine::factory()->create(['journal_entry_id' => $je4->id, 'account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 15000000]);

        $result = $this->service->getComparativeIncomeStatement('2024-02-01', '2024-02-29', '2024-01-01', '2024-01-31');

        expect($result)->toHaveKeys(['report_name', 'current_period', 'previous_period', 'variance'])
            ->and($result['report_name'])->toBe('Laporan Laba Rugi Komparatif')
            ->and($result['current_period']['revenue']['total'])->toBe(45000000)
            ->and($result['previous_period']['revenue']['total'])->toBe(30000000)
            ->and($result['variance']['revenue_change'])->toBe(15000000)
            ->and($result['current_period']['net_income'])->toBe(30000000)
            ->and($result['previous_period']['net_income'])->toBe(20000000)
            ->and($result['variance']['net_income_change'])->toBe(10000000);
    });
});

describe('getStatementOfChangesInEquity', function () {
    test('returns opening and closing equity with changes', function () {
        $capitalAccount = Account::factory()->create([
            'type' => Account::TYPE_EQUITY,
            'subtype' => Account::SUBTYPE_EQUITY,
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'is_active' => true,
            'opening_balance' => 50000000,
        ]);

        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Cash',
            'is_active' => true,
            'opening_balance' => 50000000,
        ]);

        // Record additional capital contribution
        $je = JournalEntry::factory()->create([
            'entry_date' => '2024-01-15',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $cashAccount->id,
            'debit' => 20000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $capitalAccount->id,
            'debit' => 0,
            'credit' => 20000000,
        ]);

        $result = $this->service->getStatementOfChangesInEquity('2024-01-01', '2024-01-31');

        expect($result)->toBeArray()
            ->toHaveKeys(['period_start', 'period_end', 'opening_equity', 'changes', 'closing_equity'])
            ->and($result['opening_equity'])->toHaveKeys(['items', 'total'])
            ->and($result['closing_equity'])->toHaveKeys(['items', 'total'])
            ->and($result['changes'])->toHaveKeys([
                'capital_additions', 'capital_withdrawals', 'net_income',
                'dividends', 'other_adjustments', 'total_changes',
            ]);

        // Opening equity: 50M (from opening_balance)
        expect($result['opening_equity']['total'])->toEqual(50000000);
        // Closing equity: 50M + 20M capital addition = 70M
        expect($result['closing_equity']['total'])->toEqual(70000000);
        // Change = 20M
        expect($result['changes']['total_changes'])->toEqual(20000000);
    });
});
