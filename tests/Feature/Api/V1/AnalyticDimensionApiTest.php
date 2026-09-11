<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\AnalyticBudget;
use App\Models\Accounting\AnalyticDistributionModel;
use App\Models\Accounting\AnalyticPlan;
use App\Models\Accounting\Journal;
use App\Models\Contacts\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

describe('Analytic dimensions (#147)', function () {
    it('creates lists and shows an analytic plan', function () {
        $create = $this->postJson('/api/v1/analytic-plans', [
            'code' => 'DEPT',
            'name' => 'Departments',
            'default_applicability' => 'optional',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'DEPT')
            ->assertJsonPath('data.default_applicability', 'optional');

        $this->getJson('/api/v1/analytic-plans')->assertOk()
            ->assertJsonPath('data.0.code', 'DEPT');
    });

    it('attaches an analytic account to a plan', function () {
        $plan = AnalyticPlan::factory()->create(['code' => 'PROJ']);

        $this->postJson('/api/v1/analytic-accounts', [
            'code' => 'P-01',
            'name' => 'Project 01',
            'analytic_plan_id' => $plan->id,
        ])->assertCreated()
            ->assertJsonPath('data.analytic_plan_id', $plan->id);

        $this->deleteJson("/api/v1/analytic-plans/{$plan->id}")->assertConflict();
    });

    it('matches a distribution model by account prefix', function () {
        $analytic = AnalyticAccount::factory()->create();
        AnalyticDistributionModel::factory()->create([
            'name' => 'Expense marketing',
            'account_prefix' => '5-',
            'analytic_distribution' => [(string) $analytic->id => 100],
            'sequence' => 5,
        ]);

        $expense = Account::query()->where('code', '5-2100')->firstOrFail();

        $this->getJson('/api/v1/analytic-distribution-models/match?account_id='.$expense->id)
            ->assertOk()
            ->assertJsonPath('data.analytic_distribution.'.$analytic->id, 100);
    });

    it('prefers the partner-specific distribution model over a wildcard', function () {
        $general = AnalyticAccount::factory()->create();
        $partnerAccount = AnalyticAccount::factory()->create();
        $partner = Contact::factory()->create();

        AnalyticDistributionModel::factory()->create([
            'name' => 'Wildcard',
            'analytic_distribution' => [(string) $general->id => 100],
            'sequence' => 20,
        ]);
        AnalyticDistributionModel::factory()->create([
            'name' => 'Partner split',
            'partner_id' => $partner->id,
            'analytic_distribution' => [(string) $partnerAccount->id => 100],
            'sequence' => 10,
        ]);

        $this->getJson('/api/v1/analytic-distribution-models/match?partner_id='.$partner->id)
            ->assertOk()
            ->assertJsonPath('data.analytic_distribution.'.$partnerAccount->id, 100);
    });

    it('lists analytic items exploded from posted journal distributions', function () {
        $analyticA = AnalyticAccount::factory()->create();
        $analyticB = AnalyticAccount::factory()->create();
        $cash = Account::query()->where('code', '1-1001')->firstOrFail();
        $expense = Account::query()->where('code', '5-2100')->firstOrFail();
        $journal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();

        $create = $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $journal->id,
            'entry_date' => '2026-02-15',
            'description' => 'Analytic split',
            'auto_post' => true,
            'lines' => [
                [
                    'account_id' => $expense->id,
                    'analytic_distribution' => [
                        (string) $analyticA->id => 60,
                        (string) $analyticB->id => 40,
                    ],
                    'debit' => 100_000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cash->id,
                    'debit' => 0,
                    'credit' => 100_000,
                ],
            ],
        ]);

        $create->assertCreated();

        $items = $this->getJson('/api/v1/analytic-items?from=2026-02-01&to=2026-02-28');
        $items->assertOk()->assertJsonCount(2, 'data');

        $amounts = collect($items->json('data'))->pluck('amount')->sort()->values()->all();
        expect($amounts)->toBe([40_000, 60_000]);
    });

    it('creates an analytic budget and reports actuals from analytic items', function () {
        $analytic = AnalyticAccount::factory()->create();
        $cash = Account::query()->where('code', '1-1001')->firstOrFail();
        $expense = Account::query()->where('code', '5-2100')->firstOrFail();
        $journal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();

        $this->postJson('/api/v1/journal-entries', [
            'journal_id' => $journal->id,
            'entry_date' => '2026-01-20',
            'description' => 'Budget actual',
            'auto_post' => true,
            'lines' => [
                [
                    'account_id' => $expense->id,
                    'analytic_distribution' => [(string) $analytic->id => 100],
                    'debit' => 250_000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cash->id,
                    'debit' => 0,
                    'credit' => 250_000,
                ],
            ],
        ])->assertCreated();

        $create = $this->postJson('/api/v1/analytic-budgets', [
            'name' => 'Q1 Marketing',
            'date_from' => '2026-01-01',
            'date_to' => '2026-03-31',
            'lines' => [
                [
                    'analytic_account_id' => $analytic->id,
                    'planned_amount' => 1_000_000,
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Q1 Marketing')
            ->assertJsonPath('data.lines.0.planned_amount', 1_000_000)
            ->assertJsonPath('data.lines.0.actual_amount', 250_000)
            ->assertJsonPath('data.lines.0.variance', 750_000);

        $this->putJson('/api/v1/analytic-budgets/'.$create->json('data.id'), [
            'status' => AnalyticBudget::STATUS_OPEN,
        ])->assertOk()->assertJsonPath('data.status', 'open');
    });

    it('validates required fields for analytic plans', function () {
        $this->postJson('/api/v1/analytic-plans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name']);
    });
});
