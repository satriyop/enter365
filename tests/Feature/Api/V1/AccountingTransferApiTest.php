<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingTransfer;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

function transferFixture(): array
{
    return [
        'from_journal' => Journal::query()->where('type', Journal::TYPE_BANK)->firstOrFail(),
        'to_journal' => Journal::query()->where('type', Journal::TYPE_CASH)->firstOrFail(),
        'from_account' => Account::query()->where('code', '1-1010')->firstOrFail(),
        'to_account' => Account::query()->where('code', '1-1001')->firstOrFail(),
    ];
}

describe('Accounting transfers (#148)', function () {
    it('creates lists and shows a draft transfer', function () {
        $fixture = transferFixture();

        $create = $this->postJson('/api/v1/accounting-transfers', [
            'transfer_date' => '2026-03-15',
            'from_journal_id' => $fixture['from_journal']->id,
            'to_journal_id' => $fixture['to_journal']->id,
            'from_account_id' => $fixture['from_account']->id,
            'to_account_id' => $fixture['to_account']->id,
            'amount' => 250_000,
            'memo' => 'Setor kas ke bank',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.amount', 250_000);

        $id = $create->json('data.id');

        $this->getJson('/api/v1/accounting-transfers')->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->getJson("/api/v1/accounting-transfers/{$id}")->assertOk()
            ->assertJsonPath('data.transfer_number', $create->json('data.transfer_number'));
    });

    it('posts a balanced journal from source to destination account', function () {
        $fixture = transferFixture();

        $create = $this->postJson('/api/v1/accounting-transfers', [
            'transfer_date' => '2026-03-15',
            'from_journal_id' => $fixture['from_journal']->id,
            'to_journal_id' => $fixture['to_journal']->id,
            'from_account_id' => $fixture['from_account']->id,
            'to_account_id' => $fixture['to_account']->id,
            'amount' => 150_000,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->postJson("/api/v1/accounting-transfers/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $transfer = AccountingTransfer::query()->findOrFail($id);
        $entry = JournalEntry::query()->with('lines')->findOrFail($transfer->journal_entry_id);

        expect($entry->is_posted)->toBeTrue()
            ->and($entry->source_type)->toBe(JournalEntry::SOURCE_ACCOUNTING_TRANSFER)
            ->and($entry->journal_id)->toBe($fixture['from_journal']->id)
            ->and((int) $entry->lines->sum('debit'))->toBe(150_000)
            ->and((int) $entry->lines->sum('credit'))->toBe(150_000);

        $debit = $entry->lines->firstWhere('account_id', $fixture['to_account']->id);
        $credit = $entry->lines->firstWhere('account_id', $fixture['from_account']->id);

        expect((int) $debit->debit)->toBe(150_000)
            ->and((int) $credit->credit)->toBe(150_000);
    });

    it('rejects the same source and destination account', function () {
        $fixture = transferFixture();

        $this->postJson('/api/v1/accounting-transfers', [
            'transfer_date' => '2026-03-15',
            'from_journal_id' => $fixture['from_journal']->id,
            'to_journal_id' => $fixture['to_journal']->id,
            'from_account_id' => $fixture['from_account']->id,
            'to_account_id' => $fixture['from_account']->id,
            'amount' => 10_000,
        ])->assertConflict();
    });

    it('cancels a posted transfer by reversing the journal', function () {
        $fixture = transferFixture();

        $id = $this->postJson('/api/v1/accounting-transfers', [
            'transfer_date' => '2026-03-15',
            'from_journal_id' => $fixture['from_journal']->id,
            'to_journal_id' => $fixture['to_journal']->id,
            'from_account_id' => $fixture['from_account']->id,
            'to_account_id' => $fixture['to_account']->id,
            'amount' => 80_000,
        ])->json('data.id');

        $this->postJson("/api/v1/accounting-transfers/{$id}/post")->assertOk();
        $this->postJson("/api/v1/accounting-transfers/{$id}/cancel", [
            'reason' => 'Salah akun',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $transfer = AccountingTransfer::query()->findOrFail($id);
        $entry = JournalEntry::query()->findOrFail($transfer->journal_entry_id);

        expect($entry->is_reversed)->toBeTrue();
        $this->putJson("/api/v1/accounting-transfers/{$id}", ['amount' => 90_000])->assertConflict();
    });

    it('validates required transfer fields', function () {
        $this->postJson('/api/v1/accounting-transfers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transfer_date', 'from_journal_id', 'to_journal_id', 'amount']);
    });
});
