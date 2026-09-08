<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    authenticatedAdmin();

    $this->miscJournal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();
});

describe('Analytic Account API', function () {
    it('lists analytic accounts and filters by is_active', function () {
        AnalyticAccount::factory()->create(['code' => 'PROJ-01', 'name' => 'Project One']);
        AnalyticAccount::factory()->inactive()->create(['code' => 'PROJ-99', 'name' => 'Archived']);

        $all = $this->getJson('/api/v1/analytic-accounts');
        $all->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'PROJ-01');

        $active = $this->getJson('/api/v1/analytic-accounts?is_active=1');
        $active->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PROJ-01');
    });

    it('creates an analytic account', function () {
        $response = $this->postJson('/api/v1/analytic-accounts', [
            'code' => 'MKT',
            'name' => 'Marketing',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'MKT')
            ->assertJsonPath('data.name', 'Marketing')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('analytic_accounts', [
            'code' => 'MKT',
            'name' => 'Marketing',
        ]);
    });

    it('rejects duplicate analytic account codes', function () {
        AnalyticAccount::factory()->create(['code' => 'DUP']);

        $this->postJson('/api/v1/analytic-accounts', [
            'code' => 'DUP',
            'name' => 'Duplicate',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('updates an analytic account', function () {
        $account = AnalyticAccount::factory()->create(['code' => 'OLD', 'name' => 'Old Name']);

        $this->putJson("/api/v1/analytic-accounts/{$account->id}", [
            'name' => 'New Name',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.code', 'OLD');
    });
});

describe('Journal entry analytic distribution against master', function () {
    it('accepts analytic_distribution keys that exist on analytic accounts', function () {
        $analyticA = AnalyticAccount::factory()->create(['code' => 'A1']);
        $analyticB = AnalyticAccount::factory()->create(['code' => 'A2']);
        $cashAccount = Account::where('code', '1-1001')->first();
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first()
            ?? $cashAccount;

        $distribution = [
            (string) $analyticA->id => 60,
            (string) $analyticB->id => 40,
        ];

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'JE with validated analytic ids',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'analytic_distribution' => $distribution,
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
            ->assertJsonPath('data.lines.0.analytic_distribution.'.$analyticA->id, 60)
            ->assertJsonPath('data.lines.0.analytic_distribution.'.$analyticB->id, 40);

        $line = JournalEntryLine::query()
            ->where('journal_entry_id', $response->json('data.id'))
            ->where('debit', 100000)
            ->first();

        expect($line)->not->toBeNull();
        expect($line->analytic_distribution)->toMatchArray($distribution);
    });

    it('rejects analytic_distribution keys that are not analytic accounts', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $this->miscJournal->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Unknown analytic id',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'analytic_distribution' => ['99999' => 100],
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

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.analytic_distribution']);
    });
});
