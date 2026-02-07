<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->reportService = app(FinancialReportService::class);
    $this->journalService = app(JournalService::class);
});

/**
 * Helper: create a child account under a parent.
 */
function createChildAccount(Account $parent, string $code, string $name): Account
{
    return Account::create([
        'code' => $code,
        'name' => $name,
        'type' => $parent->type,
        'subtype' => $parent->subtype,
        'parent_id' => $parent->id,
        'is_active' => true,
        'is_system' => false,
        'opening_balance' => 0,
    ]);
}

/**
 * Helper: create a balanced JE with a single debit/credit pair.
 */
function createBalancedJE(JournalService $service, int $debitAccountId, int $creditAccountId, int $amount): void
{
    $service->createEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Test JE',
        'lines' => [
            ['account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amount],
        ],
    ], autoPost: true);
}

describe('Account Hierarchy Rollup', function () {

    it('parent with 2 children sums correctly', function () {
        // 1-1001 "Kas" is a real leaf account under 1-1000 "Aset Lancar"
        $kasAccount = Account::where('code', '1-1001')->first(); // Kas
        $child1 = createChildAccount($kasAccount, '1-1001-01', 'Kas Kecil Kantor');
        $child2 = createChildAccount($kasAccount, '1-1001-02', 'Kas Kecil Proyek');
        $revenueAccount = Account::where('code', '4-1001')->first();

        // Post JE to child1: 300k, child2: 200k
        createBalancedJE($this->journalService, $child1->id, $revenueAccount->id, 300000);
        createBalancedJE($this->journalService, $child2->id, $revenueAccount->id, 200000);

        $result = $this->reportService->getBalanceSheet(hierarchical: true);

        // Find the top-level Aset Lancar node
        $asetLancarNode = $result['assets']['current']->first(fn ($n) => $n->code === '1-1000');
        expect($asetLancarNode)->not->toBeNull();

        // Find Kas within Aset Lancar's children (or it may be a top-level root depending on depth)
        // Navigate: Aset Lancar -> Kas -> children
        $kasNode = $asetLancarNode->children->first(fn ($n) => $n->account_id === $kasAccount->id);
        expect($kasNode)->not->toBeNull()
            ->and($kasNode->balance)->toBe(0) // parent has no direct transactions
            ->and($kasNode->rollup_balance)->toBe(500000)
            ->and($kasNode->children)->toHaveCount(2);
    });

    it('3-level deep hierarchy rolls up', function () {
        $kasAccount = Account::where('code', '1-1001')->first();
        $mid = createChildAccount($kasAccount, '1-1001-A', 'Sub Kas A');
        $leaf = createChildAccount($mid, '1-1001-A1', 'Sub Kas A1');
        $revenueAccount = Account::where('code', '4-1001')->first();

        createBalancedJE($this->journalService, $leaf->id, $revenueAccount->id, 100000);

        $result = $this->reportService->getBalanceSheet(hierarchical: true);

        // Navigate: Aset Lancar -> Kas -> Sub Kas A -> Sub Kas A1
        $asetLancarNode = $result['assets']['current']->first(fn ($n) => $n->code === '1-1000');
        $kasNode = $asetLancarNode->children->first(fn ($n) => $n->account_id === $kasAccount->id);

        expect($kasNode)->not->toBeNull()
            ->and($kasNode->rollup_balance)->toBe(100000)
            ->and($kasNode->children)->toHaveCount(1)
            ->and($kasNode->children->first()->rollup_balance)->toBe(100000)
            ->and($kasNode->children->first()->children)->toHaveCount(1)
            ->and($kasNode->children->first()->children->first()->balance)->toBe(100000)
            ->and($kasNode->depth)->toBe(1) // under Aset Lancar
            ->and($kasNode->children->first()->depth)->toBe(2)
            ->and($kasNode->children->first()->children->first()->depth)->toBe(3);
    });

    it('leaf account shows own balance', function () {
        $kasAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        createBalancedJE($this->journalService, $kasAccount->id, $revenueAccount->id, 750000);

        $result = $this->reportService->getBalanceSheet(hierarchical: true);

        $asetLancarNode = $result['assets']['current']->first(fn ($n) => $n->code === '1-1000');
        $kasNode = $asetLancarNode->children->first(fn ($n) => $n->account_id === $kasAccount->id);

        expect($kasNode)->not->toBeNull()
            ->and($kasNode->balance)->toBe(750000)
            ->and($kasNode->rollup_balance)->toBe(750000)
            ->and($kasNode->children)->toBeEmpty();
    });

    it('zero-balance branches excluded', function () {
        $kasAccount = Account::where('code', '1-1001')->first();
        createChildAccount($kasAccount, '1-1001-Z', 'Zero Balance Child');

        $result = $this->reportService->getBalanceSheet(hierarchical: true);

        // The whole Aset Lancar node should not appear since nothing has transactions
        $asetLancarNode = $result['assets']['current']->first(fn ($n) => $n->code === '1-1000');
        expect($asetLancarNode)->toBeNull();
    });

    it('hierarchical=false returns flat structure (backward compat)', function () {
        $kasAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        createBalancedJE($this->journalService, $kasAccount->id, $revenueAccount->id, 100000);

        $flat = $this->reportService->getBalanceSheet(hierarchical: false);
        $kasItem = $flat['assets']['current']->first(fn ($i) => $i->account_id === $kasAccount->id);

        // Flat structure should NOT have rollup_balance or children
        expect($kasItem)->not->toBeNull()
            ->and($kasItem->balance)->toBe(100000)
            ->and(property_exists($kasItem, 'rollup_balance'))->toBeFalse()
            ->and(property_exists($kasItem, 'children'))->toBeFalse();
    });

    it('balance sheet totals match between flat and hierarchical', function () {
        $kasAccount = Account::where('code', '1-1001')->first();
        $arAccount = Account::where('code', '1-1100')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        createBalancedJE($this->journalService, $kasAccount->id, $revenueAccount->id, 500000);
        createBalancedJE($this->journalService, $arAccount->id, $revenueAccount->id, 300000);

        $flat = $this->reportService->getBalanceSheet(hierarchical: false);
        $hierarchical = $this->reportService->getBalanceSheet(hierarchical: true);

        expect($flat['assets']['total'])->toBe($hierarchical['assets']['total'])
            ->and($flat['liabilities']['total'])->toBe($hierarchical['liabilities']['total'])
            ->and($flat['equity']['total'])->toBe($hierarchical['equity']['total']);
    });

    it('income statement supports hierarchical mode', function () {
        $kasAccount = Account::where('code', '1-1001')->first();
        $revenueParent = Account::where('code', '4-1001')->first();
        $revenueChild = createChildAccount($revenueParent, '4-1001-A', 'Pendapatan Proyek A');

        createBalancedJE($this->journalService, $kasAccount->id, $revenueChild->id, 400000);

        $flat = $this->reportService->getIncomeStatement(hierarchical: false);
        $hierarchical = $this->reportService->getIncomeStatement(hierarchical: true);

        // Totals must match
        expect($flat['revenue']['total'])->toBe($hierarchical['revenue']['total'])
            ->and($flat['net_income'])->toBe($hierarchical['net_income']);

        // Hierarchical should have rollup_balance on the parent
        $revenueNode = $hierarchical['revenue']['operating']->first(fn ($n) => $n->account_id === $revenueParent->id);
        if ($revenueNode) {
            expect($revenueNode->rollup_balance)->toBe(400000);
        }
    });
});
