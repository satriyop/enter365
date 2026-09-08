<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\TaxTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    authenticatedAdmin();

    $this->miscJournal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();
});

describe('Tax Tag API', function () {
    it('lists tax tags and filters by applicability', function () {
        TaxTag::factory()->base()->create(['code' => 'BASE-PPN', 'name' => 'PPN Base']);
        TaxTag::factory()->tax()->create(['code' => 'TAX-PPN', 'name' => 'PPN Tax']);
        TaxTag::factory()->tax()->inactive()->create(['code' => 'OLD', 'name' => 'Archived']);

        $this->getJson('/api/v1/tax-tags')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/tax-tags?applicability=base')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BASE-PPN');

        $this->getJson('/api/v1/tax-tags?is_active=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('creates a tax tag', function () {
        $response = $this->postJson('/api/v1/tax-tags', [
            'code' => 'PPN-OUT',
            'name' => 'PPN Keluaran',
            'applicability' => 'tax',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'PPN-OUT')
            ->assertJsonPath('data.name', 'PPN Keluaran')
            ->assertJsonPath('data.applicability', 'tax')
            ->assertJsonPath('data.is_active', true);
    });

    it('rejects duplicate tax tag codes', function () {
        TaxTag::factory()->create(['code' => 'DUP']);

        $this->postJson('/api/v1/tax-tags', [
            'code' => 'DUP',
            'name' => 'Duplicate',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });
});

describe('Journal entry tax_tag_ids against master', function () {
    it('accepts tax_tag_ids that exist on tax tags', function () {
        $tagA = TaxTag::factory()->base()->create();
        $tagB = TaxTag::factory()->tax()->create();
        $cashAccount = Account::where('code', '1-1001')->first();
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first()
            ?? $cashAccount;

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'JE with validated tax tags',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'tax_tag_ids' => [$tagA->id, $tagB->id],
                    'debit' => 100000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cashAccount->id,
                    'debit' => 0,
                    'credit' => 100000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lines.0.tax_tag_ids.0', $tagA->id)
            ->assertJsonPath('data.lines.0.tax_tag_ids.1', $tagB->id);

        $line = JournalEntryLine::query()
            ->where('journal_entry_id', $response->json('data.id'))
            ->where('debit', 100000)
            ->first();

        expect($line->tax_tag_ids)->toEqual([$tagA->id, $tagB->id]);
    });

    it('rejects tax_tag_ids that are not tax tags', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Unknown tax tag',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'tax_tag_ids' => [99999],
                    'debit' => 1000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => 1000,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.tax_tag_ids.0']);
    });
});
