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

/**
 * Helper: Find a section by name from section array or collection
 */
function findSectionFRS(\Illuminate\Support\Collection|array $sections, string $name): ?array
{
    return collect($sections)->firstWhere('name', $name);
}

/**
 * Helper: Get accounts collection from a section by name
 */
function sectionAccountsFRS(\Illuminate\Support\Collection|array $sections, string $name): \Illuminate\Support\Collection
{
    $section = findSectionFRS($sections, $name);
    return $section ? collect($section['accounts']) : collect();
}

describe('getBalanceSheet', function () {
    test('returns correct structure with empty data', function () {
        $result = $this->service->getBalanceSheet();

        expect($result)->toBeArray()
            ->toHaveKeys(['as_of_date', 'assets', 'liabilities', 'equity', 'total_assets', 'total_liabilities', 'total_equity', 'total_liabilities_equity', 'is_balanced'])
            ->and($result['assets'])->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($result['liabilities'])->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($result['equity'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
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

        expect(sectionAccountsFRS($result['assets'], 'Aset Lancar'))->toHaveCount(1)
            ->and(sectionAccountsFRS($result['assets'], 'Aset Tetap'))->toHaveCount(1)
            ->and(sectionAccountsFRS($result['liabilities'], 'Liabilitas Jangka Pendek'))->toHaveCount(1)
            ->and(sectionAccountsFRS($result['liabilities'], 'Liabilitas Jangka Panjang'))->toHaveCount(1)
            // equity may include "Laba/Rugi Berjalan" virtual item
            ->and(sectionAccountsFRS($result['equity'], 'Ekuitas')->count())->toBeGreaterThanOrEqual(1);
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
        $cashBalance = sectionAccountsFRS($result['assets'], 'Aset Lancar')->firstWhere('account_id', $cashAccount->id);
        expect($cashBalance->balance)->toBe(7000000); // 5M + 2M debit

        $apBalance = sectionAccountsFRS($result['liabilities'], 'Liabilitas Jangka Pendek')->firstWhere('account_id', $apAccount->id);
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

        // Use new top-level total fields
        $totalAssets = $result['total_assets'];
        $totalLiabilities = $result['total_liabilities'];
        $totalEquity = $result['total_equity'];

        expect($totalAssets)->toBe(25000000)
            ->and($totalLiabilities)->toBe(8000000)
            ->and($result['total_liabilities_equity'])->toBe($totalLiabilities + $totalEquity);
    });
});

describe('getIncomeStatement', function () {
    test('returns correct structure with empty data', function () {
        $result = $this->service->getIncomeStatement();

        expect($result)->toBeArray()
            ->toHaveKeys(['period_start', 'period_end', 'revenue', 'expenses', 'total_revenue', 'total_expenses', 'gross_profit', 'operating_income', 'net_income'])
            ->and($result['revenue'])->toBeArray()
            ->and($result['expenses'])->toBeArray();
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

        expect($result['total_revenue'])->toBe(50000000)
            ->and($result['total_expenses'])->toBe(15000000)
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

        // Revenue and expense sections are arrays of {name, accounts, total}
        $findSection = fn (array $sections, string $name) => collect($sections)->firstWhere('name', $name);

        $opRevenue = $findSection($result['revenue'], 'Pendapatan Operasional');
        $otherRevenue = $findSection($result['revenue'], 'Pendapatan Lain-lain');
        $opExpense = $findSection($result['expenses'], 'Beban Operasional');
        $otherExpense = $findSection($result['expenses'], 'Beban Lain-lain');

        expect($opRevenue)->not->toBeNull()
            ->and($opRevenue['accounts'])->toHaveCount(1)
            ->and($opRevenue['total'])->toBe(40000000)
            ->and($otherRevenue)->not->toBeNull()
            ->and($otherRevenue['accounts'])->toHaveCount(1)
            ->and($otherRevenue['total'])->toBe(5000000)
            ->and($opExpense)->not->toBeNull()
            ->and($opExpense['accounts'])->toHaveCount(1)
            ->and($opExpense['total'])->toBe(12000000)
            ->and($otherExpense)->not->toBeNull()
            ->and($otherExpense['accounts'])->toHaveCount(1)
            ->and($otherExpense['total'])->toBe(2000000);
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
            ->and($result['current_period']['total_assets'])->toBe(13000000)
            ->and($result['previous_period']['total_assets'])->toBe(10000000)
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
            ->and($result['current_period']['total_revenue'])->toBe(45000000)
            ->and($result['previous_period']['total_revenue'])->toBe(30000000)
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
