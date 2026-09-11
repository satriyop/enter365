<?php

use App\Contracts\Accounting\JournalServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Core\AuditLog;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

function reviewPostEntry(string $description, int $amount, bool $autoPost = true): mixed
{
    $journal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();
    $debit = Account::query()->where('code', '1-1100')->firstOrFail();
    $credit = Account::query()->where('code', '1-1010')->firstOrFail();

    return app(JournalServiceInterface::class)->createEntry([
        'journal_id' => $journal->id,
        'entry_date' => '2026-03-15',
        'description' => $description,
        'lines' => [
            ['account_id' => $debit->id, 'debit' => $amount, 'credit' => 0, 'description' => $description],
            ['account_id' => $credit->id, 'debit' => 0, 'credit' => $amount, 'description' => $description],
        ],
    ], $autoPost);
}

describe('Accounting review workspace (#150)', function () {
    it('lists journal items at line level distinct from journal entries', function () {
        reviewPostEntry('AR sale', 100_000);

        $response = $this->getJson('/api/v1/reports/review/journal-items?is_posted=1')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Journal Items');

        expect($response->json('data.meta.total'))->toBe(2)
            ->and(collect($response->json('data.rows'))->sum('debit'))->toBe(100_000)
            ->and(collect($response->json('data.rows'))->sum('credit'))->toBe(100_000)
            ->and($response->json('data.rows.0.account_code'))->not->toBeNull();
    });

    it('filters journal items by account', function () {
        reviewPostEntry('AR sale', 50_000);
        $ar = Account::query()->where('code', '1-1100')->firstOrFail();

        $this->getJson('/api/v1/reports/review/journal-items?account_id='.$ar->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.rows.0.account_code', '1-1100');
    });

    it('summarises posted journals for journal audit', function () {
        reviewPostEntry('Audit move', 80_000);

        $this->getJson('/api/v1/reports/review/journal-audit?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Journal Audit')
            ->assertJsonPath('data.totals.entry_count', 1)
            ->assertJsonPath('data.totals.debit', 80_000)
            ->assertJsonPath('data.totals.credit', 80_000);
    });

    it('lists unposted journals and draft invoices/bills as working files', function () {
        reviewPostEntry('Draft JE', 25_000, false);
        Invoice::factory()->create(['status' => DocumentStatus::Draft, 'total_amount' => 10_000]);
        Invoice::factory()->sent()->create(['total_amount' => 99_000]);
        Bill::factory()->create(['status' => DocumentStatus::Draft, 'total_amount' => 12_000]);
        Bill::factory()->received()->create(['total_amount' => 88_000]);

        $this->getJson('/api/v1/reports/review/working-files')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Working Files')
            ->assertJsonPath('data.totals.unposted_journal_entries', 1)
            ->assertJsonPath('data.totals.draft_invoices', 1)
            ->assertJsonPath('data.totals.draft_bills', 1);
    });

    it('exposes the accounting audit trail', function () {
        $entry = reviewPostEntry('Trailed', 30_000);

        $this->getJson('/api/v1/reports/review/audit-trail')
            ->assertOk()
            ->assertJsonPath('data.0.auditable_label', $entry->entry_number);

        expect(AuditLog::query()->where('auditable_id', $entry->id)->exists())->toBeTrue();
    });
});
