<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    authenticatedAdmin();
});

describe('Report export parity', function () {
    it('can export cash flow as CSV', function () {
        $response = $this->get('/api/v1/export/cash-flow?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect($response->headers->get('Content-Disposition'))->toContain('cash-flow')
            ->and($response->getContent())->toContain('Kategori')
            ->and($response->getContent())->toContain('Uraian');
    });

    it('can export changes in equity as CSV', function () {
        $response = $this->get('/api/v1/export/changes-in-equity?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect($response->headers->get('Content-Disposition'))->toContain('changes-in-equity')
            ->and($response->getContent())->toContain('Bagian');
    });

    it('can export daily cash movement as CSV', function () {
        $response = $this->get('/api/v1/export/daily-cash-movement?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect($response->headers->get('Content-Disposition'))->toContain('daily-cash-movement')
            ->and($response->getContent())->toContain('Tanggal')
            ->and($response->getContent())->toContain('Saldo Berjalan');
    });

    it('can export cash flow as JSON', function () {
        $response = $this->getJson('/api/v1/export/cash-flow?format=json');

        $response->assertOk()
            ->assertJsonStructure(['data', 'headers']);
    });
});

describe('General ledger journal and analytic filters', function () {
    it('filters general ledger by journal_id', function () {
        $cash = Account::query()->where('code', '1-1001')->firstOrFail();
        $revenue = Account::query()->where('code', '4-1001')->firstOrFail();

        $sales = Journal::query()->where('type', Journal::TYPE_SALES)->firstOrFail();
        $misc = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();

        $salesEntry = JournalEntry::factory()->posted()->create([
            'journal_id' => $sales->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Sales journal cash',
        ]);
        JournalEntryLine::factory()->forEntry($salesEntry)->forAccount($cash)->debit(500_000)->create();
        JournalEntryLine::factory()->forEntry($salesEntry)->forAccount($revenue)->credit(500_000)->create();

        $miscEntry = JournalEntry::factory()->posted()->create([
            'journal_id' => $misc->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Misc journal cash',
        ]);
        JournalEntryLine::factory()->forEntry($miscEntry)->forAccount($cash)->debit(200_000)->create();
        JournalEntryLine::factory()->forEntry($miscEntry)->forAccount($revenue)->credit(200_000)->create();

        $filtered = $this->getJson('/api/v1/reports/general-ledger?journal_id='.$sales->id);

        $filtered->assertOk()
            ->assertJsonPath('data.journal_id', $sales->id);

        $cashAccount = collect($filtered->json('data.accounts'))->firstWhere('code', '1-1001');

        expect($cashAccount)->not->toBeNull()
            ->and($cashAccount['entries'])->toHaveCount(1)
            ->and($cashAccount['entries'][0]['debit'])->toBe(500_000);

        $unfiltered = $this->getJson('/api/v1/reports/general-ledger');
        $allCash = collect($unfiltered->json('data.accounts'))->firstWhere('code', '1-1001');

        expect($allCash['entries'])->toHaveCount(2);
    });

    it('filters general ledger by analytic_account_id on line distribution', function () {
        $cash = Account::query()->where('code', '1-1001')->firstOrFail();
        $revenue = Account::query()->where('code', '4-1001')->firstOrFail();

        $withAnalytic = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($withAnalytic)->forAccount($cash)->debit(300_000)->create([
            'analytic_distribution' => ['10' => 100],
        ]);
        JournalEntryLine::factory()->forEntry($withAnalytic)->forAccount($revenue)->credit(300_000)->create();

        $withoutAnalytic = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($withoutAnalytic)->forAccount($cash)->debit(150_000)->create();
        JournalEntryLine::factory()->forEntry($withoutAnalytic)->forAccount($revenue)->credit(150_000)->create();

        $filtered = $this->getJson('/api/v1/reports/general-ledger?analytic_account_id=10');

        $filtered->assertOk()
            ->assertJsonPath('data.analytic_account_id', 10);

        $cashAccount = collect($filtered->json('data.accounts'))->firstWhere('code', '1-1001');

        expect($cashAccount)->not->toBeNull()
            ->and($cashAccount['entries'])->toHaveCount(1)
            ->and($cashAccount['entries'][0]['debit'])->toBe(300_000);
    });

    it('filters trial balance by journal_id', function () {
        $cash = Account::query()->where('code', '1-1001')->firstOrFail();
        $revenue = Account::query()->where('code', '4-1001')->firstOrFail();

        $sales = Journal::query()->where('type', Journal::TYPE_SALES)->firstOrFail();
        $misc = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();

        $salesEntry = JournalEntry::factory()->posted()->create([
            'journal_id' => $sales->id,
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($salesEntry)->forAccount($cash)->debit(400_000)->create();
        JournalEntryLine::factory()->forEntry($salesEntry)->forAccount($revenue)->credit(400_000)->create();

        $miscEntry = JournalEntry::factory()->posted()->create([
            'journal_id' => $misc->id,
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($miscEntry)->forAccount($cash)->debit(100_000)->create();
        JournalEntryLine::factory()->forEntry($miscEntry)->forAccount($revenue)->credit(100_000)->create();

        $filtered = $this->getJson('/api/v1/reports/trial-balance?journal_id='.$sales->id);

        $filtered->assertOk()
            ->assertJsonPath('data.journal_id', $sales->id);

        $cashRow = collect($filtered->json('data.accounts'))->firstWhere('code', '1-1001');

        expect($cashRow['debit_balance'])->toBe(400_000);
    });
});

describe('Comparative cash flow', function () {
    it('returns current and previous periods when compare_previous_period is set', function () {
        $response = $this->getJson('/api/v1/reports/cash-flow?compare_previous_period=1&start_date=2026-01-01&end_date=2026-01-31');

        $response->assertOk()
            ->assertJsonPath('data.report_name', 'Laporan Arus Kas Komparatif')
            ->assertJsonStructure([
                'data' => [
                    'current_period' => ['period', 'net_cash_change', 'opening_balance', 'closing_balance'],
                    'previous_period' => ['period', 'net_cash_change'],
                    'variance' => ['net_cash_change', 'opening_balance_change', 'closing_balance_change'],
                ],
            ]);
    });
});
