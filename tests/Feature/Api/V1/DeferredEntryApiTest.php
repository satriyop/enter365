<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\DeferredEntry;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

function deferredExpenseAccounts(): array
{
    return [
        'deferred' => Account::query()->where('code', '1-1501')->firstOrFail(),
        'recognition' => Account::query()->where('code', '5-2100')->firstOrFail(),
        'counterpart' => Account::query()->where('code', '1-1002')->firstOrFail(),
    ];
}

function deferredRevenueAccounts(): array
{
    return [
        'deferred' => Account::query()->where('code', '2-1600')->firstOrFail(),
        'recognition' => Account::query()->where('code', '4-2002')->firstOrFail(),
        'counterpart' => Account::query()->where('code', '1-1002')->firstOrFail(),
    ];
}

describe('Deferred expenses and revenues (#144)', function () {
    it('creates lists and shows a deferred expense', function () {
        $accounts = deferredExpenseAccounts();

        $create = $this->postJson('/api/v1/deferred-expenses', [
            'code' => 'DEF-SEWA-1',
            'name' => 'Sewa gudang 12 bulan',
            'amount' => 1_200_000,
            'duration_months' => 12,
            'start_date' => '2026-01-15',
            'deferred_account_id' => $accounts['deferred']->id,
            'recognition_account_id' => $accounts['recognition']->id,
            'counterpart_account_id' => $accounts['counterpart']->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'DEF-SEWA-1')
            ->assertJsonPath('data.kind', 'expense')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.amount', 1_200_000);

        $this->getJson('/api/v1/deferred-expenses')->assertOk()
            ->assertJsonPath('data.0.code', 'DEF-SEWA-1');
    });

    it('confirms a deferred expense, posts origination, and builds equal recognition lines', function () {
        $accounts = deferredExpenseAccounts();
        $entry = DeferredEntry::factory()->expense()->create([
            'amount' => 1_200_000,
            'duration_months' => 3,
            'start_date' => '2026-01-10',
            'deferred_account_id' => $accounts['deferred']->id,
            'recognition_account_id' => $accounts['recognition']->id,
            'counterpart_account_id' => $accounts['counterpart']->id,
        ]);

        $confirm = $this->postJson("/api/v1/deferred-expenses/{$entry->id}/confirm");

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.remaining_amount', 1_200_000)
            ->assertJsonCount(3, 'data.lines')
            ->assertJsonPath('data.lines.0.amount', 400_000)
            ->assertJsonPath('data.lines.2.remaining_amount', 0)
            ->assertJsonPath('data.lines.0.recognition_date', '2026-01-31');

        $je = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_DEFERRED_ENTRY)
            ->where('source_id', $entry->id)
            ->with('lines')
            ->firstOrFail();

        expect($je->lines->firstWhere('account_id', $accounts['deferred']->id)?->debit)->toBe(1_200_000)
            ->and($je->lines->firstWhere('account_id', $accounts['counterpart']->id)?->credit)->toBe(1_200_000);
    });

    it('posts the next expense recognition and updates remaining amount', function () {
        $accounts = deferredExpenseAccounts();
        $entry = DeferredEntry::factory()->expense()->create([
            'amount' => 1_200_000,
            'duration_months' => 3,
            'start_date' => now()->startOfMonth()->toDateString(),
            'deferred_account_id' => $accounts['deferred']->id,
            'recognition_account_id' => $accounts['recognition']->id,
            'counterpart_account_id' => $accounts['counterpart']->id,
        ]);

        $this->postJson("/api/v1/deferred-expenses/{$entry->id}/confirm")->assertOk();
        $post = $this->postJson("/api/v1/deferred-expenses/{$entry->id}/post-recognition");

        $post->assertOk()
            ->assertJsonPath('data.remaining_amount', 800_000)
            ->assertJsonPath('data.lines.0.status', 'posted')
            ->assertJsonPath('data.status', 'running');
    });

    it('confirms deferred revenue as Dr bank / Cr unearned', function () {
        $accounts = deferredRevenueAccounts();
        $entry = DeferredEntry::factory()->revenue()->create([
            'amount' => 600_000,
            'duration_months' => 2,
            'start_date' => '2026-01-10',
            'deferred_account_id' => $accounts['deferred']->id,
            'recognition_account_id' => $accounts['recognition']->id,
            'counterpart_account_id' => $accounts['counterpart']->id,
        ]);

        $this->postJson("/api/v1/deferred-revenues/{$entry->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.kind', 'revenue')
            ->assertJsonPath('data.status', 'running');

        $je = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_DEFERRED_ENTRY)
            ->where('source_id', $entry->id)
            ->with('lines')
            ->firstOrFail();

        expect($je->lines->firstWhere('account_id', $accounts['counterpart']->id)?->debit)->toBe(600_000)
            ->and($je->lines->firstWhere('account_id', $accounts['deferred']->id)?->credit)->toBe(600_000);

        $this->getJson('/api/v1/deferred-expenses')->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/deferred-revenues')->assertOk()
            ->assertJsonPath('data.0.id', $entry->id);
    });

    it('validates required fields when creating a deferred expense', function () {
        $this->postJson('/api/v1/deferred-expenses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'amount', 'duration_months', 'start_date']);
    });

    it('refuses to update or delete a running deferred expense', function () {
        $accounts = deferredExpenseAccounts();
        $entry = DeferredEntry::factory()->expense()->create([
            'amount' => 1_200_000,
            'duration_months' => 2,
            'start_date' => '2026-01-10',
            'deferred_account_id' => $accounts['deferred']->id,
            'recognition_account_id' => $accounts['recognition']->id,
            'counterpart_account_id' => $accounts['counterpart']->id,
        ]);

        $this->postJson("/api/v1/deferred-expenses/{$entry->id}/confirm")->assertOk();

        $this->putJson("/api/v1/deferred-expenses/{$entry->id}", ['name' => 'Nope'])->assertConflict();
        $this->deleteJson("/api/v1/deferred-expenses/{$entry->id}")->assertConflict();
    });

    it('closes the deferred expense after the last recognition is posted', function () {
        $accounts = deferredExpenseAccounts();
        $entry = DeferredEntry::factory()->expense()->create([
            'amount' => 1_200_000,
            'duration_months' => 2,
            'start_date' => now()->startOfMonth()->toDateString(),
            'deferred_account_id' => $accounts['deferred']->id,
            'recognition_account_id' => $accounts['recognition']->id,
            'counterpart_account_id' => $accounts['counterpart']->id,
        ]);

        $this->postJson("/api/v1/deferred-expenses/{$entry->id}/confirm")->assertOk();
        $this->postJson("/api/v1/deferred-expenses/{$entry->id}/post-recognition")->assertOk();
        $last = $this->postJson("/api/v1/deferred-expenses/{$entry->id}/post-recognition");

        $last->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.remaining_amount', 0);
    });
});
