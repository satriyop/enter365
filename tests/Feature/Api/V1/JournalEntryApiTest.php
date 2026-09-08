<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate as admin (has all permissions)
    authenticatedAdmin();

    $this->miscJournal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();
});

describe('Journal Entry API', function () {

    it('can list all journal entries', function () {
        JournalEntry::factory()->count(10)->create();

        $this->assertMaxQueries(20, function () {
            $response = $this->getJson('/api/v1/journal-entries');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/journal-entries');
        $response->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'entry_number',
                        'entry_date',
                        'description',
                        'is_posted',
                        'total_debit',
                        'total_credit',
                    ],
                ],
            ]);
    });

    it('can filter journal entries by posted status', function () {
        JournalEntry::factory()->count(2)->create();
        JournalEntry::factory()->posted()->count(3)->create();

        $response = $this->getJson('/api/v1/journal-entries?is_posted=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter journal entries by date range', function () {
        JournalEntry::factory()->create(['entry_date' => '2024-01-15']);
        JournalEntry::factory()->create(['entry_date' => '2024-02-15']);
        JournalEntry::factory()->create(['entry_date' => '2024-03-15']);

        $response = $this->getJson('/api/v1/journal-entries?start_date=2024-02-01&end_date=2024-02-28');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a balanced journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test Journal Entry',
            'reference' => 'TEST-001',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'description' => 'Cash received',
                    'debit' => 1000000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'description' => 'Revenue',
                    'debit' => 0,
                    'credit' => 1000000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Test Journal Entry')
            ->assertJsonPath('data.is_balanced', true)
            ->assertJsonPath('data.journal_id', $this->miscJournal->id)
            ->assertJsonCount(2, 'data.lines');
    });

    it('can create and auto-post journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Auto-posted entry',
            'auto_post' => true,
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 500000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_posted', true);
    });

    it('rejects unbalanced journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 1000000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 500000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    });

    it('requires at least two lines', function () {
        $cashAccount = Account::where('code', '1-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Single line entry',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 1000000, 'credit' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    });

    it('requires journal_id when creating journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Missing journal',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['journal_id']);
    });

    it('can filter journal entries by journal type', function () {
        $sales = Journal::query()->where('type', Journal::TYPE_SALES)->firstOrFail();
        $misc = $this->miscJournal;

        JournalEntry::factory()->create(['journal_id' => $sales->id]);
        JournalEntry::factory()->count(2)->create(['journal_id' => $misc->id]);

        $response = $this->getJson('/api/v1/journal-entries?journal_type=sales');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('numbers new entries with journal sequence prefix', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Prefixed entry',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response->assertCreated();
        expect($response->json('data.entry_number'))->toStartWith('MISC-');
        expect($response->json('data.journal_id'))->toBe($this->miscJournal->id);
    });

    it('can show a journal entry with lines', function () {
        $entry = JournalEntry::factory()->create();
        $account = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->credit(100000)->create();

        $response = $this->getJson("/api/v1/journal-entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'entry_number', 'entry_date', 'description', 'is_posted', 'lines',
                ],
            ]);
    });

    it('can post a draft journal entry', function () {
        $entry = JournalEntry::factory()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account1)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account2)->credit(100000)->create();

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/post");

        $response->assertOk()
            ->assertJsonPath('data.is_posted', true);
    });

    it('cannot post already posted entry', function () {
        $entry = JournalEntry::factory()->posted()->create();
        $account = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->credit(100000)->create();

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/post");

        $response->assertUnprocessable();
    });

    it('can reverse a posted journal entry', function () {
        $entry = JournalEntry::factory()->posted()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account1)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account2)->credit(100000)->create();

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/reverse", [
            'description' => 'Custom reversal description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_posted', true)
            ->assertJsonPath('data.description', 'Custom reversal description');

        // Verify original entry is marked as reversed
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'is_reversed' => true,
        ]);
    });

    it('cannot reverse unposted entry', function () {
        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/reverse");

        $response->assertStatus(409);
    });
    it('can create journal entry lines with optional partner_id', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $arAccount = Account::where('code', '1-2001')->first()
            ?? Account::where('code', '1-1101')->first()
            ?? $cashAccount;
        $revenueAccount = Account::where('code', '4-1001')->first();
        $partner = \App\Models\Contacts\Contact::factory()->create();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'JE with partner on line',
            'lines' => [
                [
                    'account_id' => $arAccount->id,
                    'partner_id' => $partner->id,
                    'description' => 'AR line',
                    'debit' => 250000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'description' => 'Revenue',
                    'debit' => 0,
                    'credit' => 250000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lines.0.partner_id', $partner->id)
            ->assertJsonPath('data.lines.1.partner_id', null);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $response->json('data.id'),
            'partner_id' => $partner->id,
            'debit' => 250000,
        ]);
    });

    it('shows partner on journal entry lines when loaded', function () {
        $partner = \App\Models\Contacts\Contact::factory()->create(['name' => 'Partner Co']);
        $entry = JournalEntry::factory()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account1)->debit(100000)->create([
            'partner_id' => $partner->id,
        ]);
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account2)->credit(100000)->create();

        $response = $this->getJson("/api/v1/journal-entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonPath('data.lines.0.partner_id', $partner->id)
            ->assertJsonPath('data.lines.0.partner.id', $partner->id)
            ->assertJsonPath('data.lines.0.partner.name', 'Partner Co')
            ->assertJsonPath('data.lines.1.partner_id', null);
    });

    it('can filter journal entries by partner_id on lines', function () {
        $partner = \App\Models\Contacts\Contact::factory()->create();
        $other = \App\Models\Contacts\Contact::factory()->create();

        $withPartner = JournalEntry::factory()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($withPartner)->forAccount($account1)->debit(50000)->create([
            'partner_id' => $partner->id,
        ]);
        JournalEntryLine::factory()->forEntry($withPartner)->forAccount($account2)->credit(50000)->create();

        $without = JournalEntry::factory()->create();
        JournalEntryLine::factory()->forEntry($without)->forAccount($account1)->debit(50000)->create([
            'partner_id' => $other->id,
        ]);
        JournalEntryLine::factory()->forEntry($without)->forAccount($account2)->credit(50000)->create();

        $response = $this->getJson('/api/v1/journal-entries?partner_id='.$partner->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withPartner->id);
    });

    it('can create journal entry lines with optional analytic_distribution and tax_tag_ids', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first()
            ?? $cashAccount;
        $payableAccount = Account::where('code', '2-1001')->first()
            ?? Account::where('code', '2-1101')->first()
            ?? $cashAccount;

        $analyticA = AnalyticAccount::factory()->create();
        $analyticB = AnalyticAccount::factory()->create();
        $distribution = [
            (string) $analyticA->id => 60,
            (string) $analyticB->id => 40,
        ];
        $taxTags = [101, 202];

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'JE with analytic + tax grids on line',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'analytic_distribution' => $distribution,
                    'tax_tag_ids' => $taxTags,
                    'description' => 'Expense with dimensions',
                    'debit' => 100000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $payableAccount->id,
                    'description' => 'Payable',
                    'debit' => 0,
                    'credit' => 100000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lines.0.analytic_distribution.'.$analyticA->id, 60)
            ->assertJsonPath('data.lines.0.analytic_distribution.'.$analyticB->id, 40)
            ->assertJsonPath('data.lines.0.tax_tag_ids.0', 101)
            ->assertJsonPath('data.lines.0.tax_tag_ids.1', 202)
            ->assertJsonPath('data.lines.1.analytic_distribution', null)
            ->assertJsonPath('data.lines.1.tax_tag_ids', null);

        $line = JournalEntryLine::query()
            ->where('journal_entry_id', $response->json('data.id'))
            ->where('debit', 100000)
            ->first();

        expect($line)->not->toBeNull();
        expect($line->analytic_distribution)->toMatchArray($distribution);
        expect($line->tax_tag_ids)->toEqual($taxTags);
    });

    it('round-trips analytic_distribution as a JSON object map, not a value list', function () {
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first()
            ?? Account::where('code', '1-1001')->first();
        $payableAccount = Account::where('code', '2-1001')->first()
            ?? Account::where('code', '2-1101')->first()
            ?? Account::where('code', '1-1001')->first();

        $analyticA = AnalyticAccount::factory()->create();
        $analyticB = AnalyticAccount::factory()->create();
        $distribution = [
            (string) $analyticA->id => 60,
            (string) $analyticB->id => 40,
        ];

        $created = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Analytic distribution map fidelity',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'analytic_distribution' => $distribution,
                    'tax_tag_ids' => [101],
                    'description' => 'Expense with analytic map',
                    'debit' => 100000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $payableAccount->id,
                    'description' => 'Payable',
                    'debit' => 0,
                    'credit' => 100000,
                ],
            ],
        ]);

        $created->assertCreated();
        $createdBody = json_decode($created->getContent());
        $createdLine = collect($createdBody->data->lines)->first(fn ($row) => isset($row->tax_tag_ids) && $row->tax_tag_ids == [101]);
        expect($createdLine->analytic_distribution)->toBeObject();
        expect(get_object_vars($createdLine->analytic_distribution))->toBe($distribution);
        expect($createdLine->tax_tag_ids)->toBe([101]);
        $entryId = $created->json('data.id');

        $show = $this->getJson("/api/v1/journal-entries/{$entryId}");
        $show->assertOk();

        $body = json_decode($show->getContent());
        $line = collect($body->data->lines)->first(fn ($row) => isset($row->tax_tag_ids) && $row->tax_tag_ids == [101]);

        expect($line)->not->toBeNull();
        expect($line->analytic_distribution)->toBeObject();
        expect(get_object_vars($line->analytic_distribution))->toBe($distribution);
        expect($line->tax_tag_ids)->toBeArray();
        expect($line->tax_tag_ids)->toBe([101]);

        $stored = JournalEntryLine::query()
            ->where('journal_entry_id', $entryId)
            ->where('debit', 100000)
            ->first();
        $raw = $stored?->getRawOriginal('analytic_distribution');
        expect(is_string($raw) ? ltrim($raw) : json_encode($raw))->toStartWith('{');
        expect($stored->analytic_distribution)->toMatchArray($distribution);
    });

    it('shows analytic_distribution and tax_tag_ids on journal entry lines', function () {
        $entry = JournalEntry::factory()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account1)->debit(75000)->create([
            'analytic_distribution' => ['5' => 100],
            'tax_tag_ids' => [7, 8],
        ]);
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account2)->credit(75000)->create();

        $response = $this->getJson("/api/v1/journal-entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonPath('data.lines.0.analytic_distribution.5', 100)
            ->assertJsonPath('data.lines.0.tax_tag_ids.0', 7)
            ->assertJsonPath('data.lines.0.tax_tag_ids.1', 8)
            ->assertJsonPath('data.lines.1.analytic_distribution', null)
            ->assertJsonPath('data.lines.1.tax_tag_ids', null);
    });

    it('rejects invalid analytic_distribution and tax_tag_ids on lines', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $badAnalytic = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Bad analytic',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'analytic_distribution' => ['1' => 150],
                    'debit' => 1000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => 1000,
                ],
            ],
        ]);
        $badAnalytic->assertStatus(422)->assertJsonValidationErrors(['lines.0.analytic_distribution.1']);

        $badTags = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Bad tax tags',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'tax_tag_ids' => ['not-an-id'],
                    'debit' => 1000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => 1000,
                ],
            ],
        ]);
        $badTags->assertStatus(422)->assertJsonValidationErrors(['lines.0.tax_tag_ids.0']);
    });

});
