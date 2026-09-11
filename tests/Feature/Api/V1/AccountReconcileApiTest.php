<?php

use App\Contracts\Accounting\JournalServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountReconciliation;
use App\Models\Accounting\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

function reconcilableReceivable(): Account
{
    $account = Account::query()->where('code', '1-1100')->firstOrFail();
    $account->update(['allow_reconciliation' => true]);

    return $account->fresh();
}

function postPairedLines(Account $account, int $amount): array
{
    $journal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();
    $offset = Account::query()->where('code', '1-1010')->firstOrFail();
    $service = app(JournalServiceInterface::class);

    $debitEntry = $service->createEntry([
        'journal_id' => $journal->id,
        'entry_date' => '2026-03-10',
        'description' => 'Piutang',
        'lines' => [
            ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0, 'description' => 'AR debit'],
            ['account_id' => $offset->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Bank'],
        ],
    ], true);

    $creditEntry = $service->createEntry([
        'journal_id' => $journal->id,
        'entry_date' => '2026-03-12',
        'description' => 'Pelunasan',
        'lines' => [
            ['account_id' => $offset->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Bank'],
            ['account_id' => $account->id, 'debit' => 0, 'credit' => $amount, 'description' => 'AR credit'],
        ],
    ], true);

    return [
        'debit' => $debitEntry->lines->firstWhere('account_id', $account->id),
        'credit' => $creditEntry->lines->firstWhere('account_id', $account->id),
    ];
}

describe('General reconcile workspace (#148)', function () {
    it('lists reconcilable accounts and outstanding lines', function () {
        $account = reconcilableReceivable();
        postPairedLines($account, 100_000);

        $this->getJson('/api/v1/reconcile/accounts')->assertOk()
            ->assertJsonFragment(['code' => '1-1100']);

        $lines = $this->getJson('/api/v1/reconcile/lines?account_id='.$account->id)
            ->assertOk()
            ->json('data');

        expect($lines)->toHaveCount(2)
            ->and(collect($lines)->sum('residual'))->toBe(200_000);
    });

    it('reconciles matching debit and credit residuals', function () {
        $account = reconcilableReceivable();
        $pair = postPairedLines($account, 75_000);

        $create = $this->postJson('/api/v1/reconcile', [
            'account_id' => $account->id,
            'items' => [
                ['journal_entry_line_id' => $pair['debit']->id],
                ['journal_entry_line_id' => $pair['credit']->id],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.amount', 75_000);

        expect($pair['debit']->fresh()->residualAmount())->toBe(0)
            ->and($pair['credit']->fresh()->residualAmount())->toBe(0);

        $this->getJson('/api/v1/reconcile/lines?account_id='.$account->id)
            ->assertOk()
            ->assertJsonPath('data', []);
    });

    it('rejects unbalanced selections', function () {
        $account = reconcilableReceivable();
        $pair = postPairedLines($account, 50_000);

        $this->postJson('/api/v1/reconcile', [
            'account_id' => $account->id,
            'items' => [
                ['journal_entry_line_id' => $pair['debit']->id, 'amount' => 50_000],
                ['journal_entry_line_id' => $pair['credit']->id, 'amount' => 10_000],
            ],
        ])->assertConflict();
    });

    it('unreconciles and restores residuals', function () {
        $account = reconcilableReceivable();
        $pair = postPairedLines($account, 40_000);

        $id = $this->postJson('/api/v1/reconcile', [
            'account_id' => $account->id,
            'items' => [
                ['journal_entry_line_id' => $pair['debit']->id],
                ['journal_entry_line_id' => $pair['credit']->id],
            ],
        ])->json('data.id');

        $this->postJson("/api/v1/reconcile/{$id}/unreconcile")->assertOk();

        expect(AccountReconciliation::query()->find($id))->toBeNull()
            ->and($pair['debit']->fresh()->residualAmount())->toBe(40_000)
            ->and($pair['credit']->fresh()->residualAmount())->toBe(40_000);
    });

    it('refuses accounts that do not allow reconciliation', function () {
        $account = Account::query()->where('code', '5-2100')->firstOrFail();
        $pair = postPairedLines($account, 20_000);

        $this->postJson('/api/v1/reconcile', [
            'account_id' => $account->id,
            'items' => [
                ['journal_entry_line_id' => $pair['debit']->id],
                ['journal_entry_line_id' => $pair['credit']->id],
            ],
        ])->assertConflict();
    });

    it('validates reconcile payload', function () {
        $this->postJson('/api/v1/reconcile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'items']);
    });
});
