<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Authenticate user
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Budget CRUD', function () {

    it('can list all budgets', function () {
        Budget::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/budgets');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter budgets by fiscal period', function () {
        $period1 = FiscalPeriod::factory()->create();
        $period2 = FiscalPeriod::factory()->create();
        Budget::factory()->count(2)->forPeriod($period1)->create();
        Budget::factory()->count(3)->forPeriod($period2)->create();

        $response = $this->getJson("/api/v1/budgets?fiscal_period_id={$period1->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter budgets by status', function () {
        Budget::factory()->count(2)->draft()->create();
        Budget::factory()->count(3)->approved()->create();

        $response = $this->getJson('/api/v1/budgets?status=approved');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a budget', function () {
        $period = FiscalPeriod::factory()->create();

        $response = $this->postJson('/api/v1/budgets', [
            'name' => 'Anggaran 2025',
            'description' => 'Anggaran tahunan',
            'fiscal_period_id' => $period->id,
            'type' => 'annual',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Anggaran 2025')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('budgets', [
            'name' => 'Anggaran 2025',
        ]);
    });

    it('can create a budget with lines', function () {
        $period = FiscalPeriod::factory()->create();
        $revenueAccount = Account::factory()->create(['type' => Account::TYPE_REVENUE]);
        $expenseAccount = Account::factory()->create(['type' => Account::TYPE_EXPENSE]);

        $response = $this->postJson('/api/v1/budgets', [
            'name' => 'Anggaran 2025',
            'fiscal_period_id' => $period->id,
            'type' => 'annual',
            'lines' => [
                [
                    'account_id' => $revenueAccount->id,
                    'annual_amount' => 120000000,
                ],
                [
                    'account_id' => $expenseAccount->id,
                    'annual_amount' => 84000000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_revenue', 120000000)
            ->assertJsonPath('data.total_expense', 84000000)
            ->assertJsonPath('data.net_budget', 36000000);

        $this->assertDatabaseCount('budget_lines', 2);
    });

    it('validates required fields when creating budget', function () {
        $response = $this->postJson('/api/v1/budgets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'fiscal_period_id', 'type']);
    });

    it('can show a budget with lines', function () {
        $budget = Budget::factory()->create();
        $account = Account::factory()->create();
        BudgetLine::factory()->forBudget($budget)->forAccount($account)->create();

        $response = $this->getJson("/api/v1/budgets/{$budget->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $budget->id)
            ->assertJsonCount(1, 'data.lines');
    });

    it('can update a draft budget', function () {
        $budget = Budget::factory()->draft()->create();

        $response = $this->putJson("/api/v1/budgets/{$budget->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    });

    it('cannot update an approved budget', function () {
        $budget = Budget::factory()->approved()->create();

        $response = $this->putJson("/api/v1/budgets/{$budget->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Anggaran yang sudah disetujui atau ditutup tidak bisa diubah.');
    });

    it('can delete a draft budget', function () {
        $budget = Budget::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/budgets/{$budget->id}");

        $response->assertOk();
        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
    });

    it('cannot delete an approved budget', function () {
        $budget = Budget::factory()->approved()->create();

        $response = $this->deleteJson("/api/v1/budgets/{$budget->id}");

        $response->assertStatus(422);
    });
});

describe('Budget Lines', function () {

    it('can add a line to a budget', function () {
        $budget = Budget::factory()->draft()->create();
        $account = Account::factory()->create(['type' => Account::TYPE_EXPENSE]);

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/lines", [
            'account_id' => $account->id,
            'annual_amount' => 12000000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.annual_amount', 12000000);

        // Check monthly distribution (12000000 / 12 = 1000000)
        expect($response->json('data.jan_amount'))->toBe(1000000);
    });

    it('can add a line with monthly amounts', function () {
        $budget = Budget::factory()->draft()->create();
        $account = Account::factory()->create(['type' => Account::TYPE_REVENUE]);

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/lines", [
            'account_id' => $account->id,
            'jan_amount' => 1000000,
            'feb_amount' => 1500000,
            'mar_amount' => 2000000,
            'apr_amount' => 1000000,
            'may_amount' => 1000000,
            'jun_amount' => 1000000,
            'jul_amount' => 1000000,
            'aug_amount' => 1000000,
            'sep_amount' => 1000000,
            'oct_amount' => 1000000,
            'nov_amount' => 1000000,
            'dec_amount' => 1500000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.annual_amount', 14000000)
            ->assertJsonPath('data.jan_amount', 1000000)
            ->assertJsonPath('data.feb_amount', 1500000);
    });

    it('cannot add duplicate account to budget', function () {
        $budget = Budget::factory()->draft()->create();
        $account = Account::factory()->create();

        BudgetLine::factory()->forBudget($budget)->forAccount($account)->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/lines", [
            'account_id' => $account->id,
            'annual_amount' => 12000000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Akun sudah ada dalam anggaran ini.');
    });

    it('can update a budget line', function () {
        $budget = Budget::factory()->draft()->create();
        $line = BudgetLine::factory()->forBudget($budget)->create();

        $response = $this->putJson("/api/v1/budgets/{$budget->id}/lines/{$line->id}", [
            'jan_amount' => 2000000,
            'feb_amount' => 2000000,
        ]);

        $response->assertOk();
        expect($line->fresh()->jan_amount)->toBe(2000000);
    });

    it('can update line with even distribution', function () {
        $budget = Budget::factory()->draft()->create();
        $line = BudgetLine::factory()->forBudget($budget)->create();

        $response = $this->putJson("/api/v1/budgets/{$budget->id}/lines/{$line->id}", [
            'annual_amount' => 24000000,
            'distribute_evenly' => true,
        ]);

        $response->assertOk();
        $fresh = $line->fresh();
        expect($fresh->jan_amount)->toBe(2000000)
            ->and($fresh->annual_amount)->toBe(24000000);
    });

    it('can delete a budget line', function () {
        $budget = Budget::factory()->draft()->create();
        $line = BudgetLine::factory()->forBudget($budget)->create();

        $response = $this->deleteJson("/api/v1/budgets/{$budget->id}/lines/{$line->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('budget_lines', ['id' => $line->id]);
    });

    it('cannot modify lines of approved budget', function () {
        $budget = Budget::factory()->approved()->create();
        $account = Account::factory()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/lines", [
            'account_id' => $account->id,
            'annual_amount' => 12000000,
        ]);

        $response->assertStatus(422);
    });
});

describe('Budget Workflow', function () {

    it('can approve a budget', function () {
        $budget = Budget::factory()->draft()->create();
        BudgetLine::factory()->forBudget($budget)->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        expect($budget->fresh()->isApproved())->toBeTrue();
    });

    it('cannot approve budget without lines', function () {
        $budget = Budget::factory()->draft()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/approve");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Anggaran harus memiliki minimal satu baris.');
    });

    it('cannot approve already approved budget', function () {
        $budget = Budget::factory()->approved()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/approve");

        $response->assertStatus(422);
    });

    it('can reopen an approved budget', function () {
        $budget = Budget::factory()->approved()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/reopen");

        $response->assertOk()
            ->assertJsonPath('data.status', 'draft');
    });

    it('cannot reopen a closed budget', function () {
        $budget = Budget::factory()->closed()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/reopen");

        $response->assertStatus(422);
    });

    it('can close an approved budget', function () {
        $budget = Budget::factory()->approved()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');
    });

    it('cannot close a draft budget', function () {
        $budget = Budget::factory()->draft()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/close");

        $response->assertStatus(422);
    });

    it('can copy a budget to new period', function () {
        $budget = Budget::factory()->create();
        BudgetLine::factory()->count(3)->forBudget($budget)->create();
        $newPeriod = FiscalPeriod::factory()->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/copy", [
            'fiscal_period_id' => $newPeriod->id,
            'name' => 'Anggaran Baru',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Anggaran Baru')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseCount('budgets', 2);
    });

    it('cannot copy to period that already has budget', function () {
        $budget = Budget::factory()->create();
        $existingPeriod = FiscalPeriod::factory()->create();
        Budget::factory()->forPeriod($existingPeriod)->create();

        $response = $this->postJson("/api/v1/budgets/{$budget->id}/copy", [
            'fiscal_period_id' => $existingPeriod->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Sudah ada anggaran untuk periode ini.');
    });
});

describe('Budget Reports', function () {

    it('can get budget vs actual comparison', function () {
        $period = FiscalPeriod::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);
        $budget = Budget::factory()->forPeriod($period)->approved()->create();
        $account = Account::factory()->create(['type' => Account::TYPE_EXPENSE]);
        BudgetLine::factory()
            ->forBudget($budget)
            ->forAccount($account)
            ->withAnnualAmount(12000000)
            ->create();

        $response = $this->getJson("/api/v1/budgets/{$budget->id}/comparison");

        $response->assertOk()
            ->assertJsonPath('budget.id', $budget->id)
            ->assertJsonStructure([
                'comparison' => [
                    '*' => [
                        'account_id',
                        'account_code',
                        'account_name',
                        'budget_amount',
                        'actual_amount',
                        'variance',
                        'variance_percent',
                        'is_over_budget',
                    ],
                ],
            ]);
    });

    it('can get monthly breakdown', function () {
        $budget = Budget::factory()->create();
        BudgetLine::factory()->forBudget($budget)->create();

        $response = $this->getJson("/api/v1/budgets/{$budget->id}/monthly-breakdown");

        $response->assertOk()
            ->assertJsonStructure([
                'monthly_breakdown' => [
                    1 => ['month', 'month_name', 'budget', 'actual', 'variance'],
                    2 => ['month', 'month_name', 'budget', 'actual', 'variance'],
                ],
            ]);
    });

    it('can get budget summary', function () {
        $period = FiscalPeriod::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);
        $budget = Budget::factory()->forPeriod($period)->create([
            'total_revenue' => 100000000,
            'total_expense' => 80000000,
            'net_budget' => 20000000,
        ]);

        $response = $this->getJson("/api/v1/budgets/{$budget->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'budget' => ['name', 'type', 'status'],
                'annual' => ['budget_revenue', 'budget_expense', 'budget_net'],
                'ytd' => ['budget_revenue', 'actual_revenue', 'variance_revenue'],
            ]);
    });

    it('can get over-budget accounts', function () {
        $period = FiscalPeriod::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);
        $budget = Budget::factory()->forPeriod($period)->approved()->create();
        $account = Account::factory()->create(['type' => Account::TYPE_EXPENSE]);
        BudgetLine::factory()
            ->forBudget($budget)
            ->forAccount($account)
            ->withAnnualAmount(1200000) // 100k/month
            ->create();

        // Create actual expense that exceeds budget
        $journalEntry = JournalEntry::factory()->create([
            'fiscal_period_id' => $period->id,
            'is_posted' => true,
            'entry_date' => now(),
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $account->id,
            'debit' => 5000000, // Much higher than budget
            'credit' => 0,
        ]);

        $response = $this->getJson("/api/v1/budgets/{$budget->id}/over-budget");

        $response->assertOk()
            ->assertJsonPath('over_budget_count', 1);
    });
});

describe('Budget Line Model', function () {

    it('can get monthly amounts as array', function () {
        $line = BudgetLine::factory()->create([
            'jan_amount' => 1000000,
            'feb_amount' => 2000000,
            'mar_amount' => 3000000,
        ]);

        $amounts = $line->getMonthlyAmounts();

        expect($amounts[1])->toBe(1000000)
            ->and($amounts[2])->toBe(2000000)
            ->and($amounts[3])->toBe(3000000);
    });

    it('can get quarter amounts', function () {
        $line = BudgetLine::factory()->create([
            'jan_amount' => 1000000,
            'feb_amount' => 1000000,
            'mar_amount' => 1000000,
            'apr_amount' => 2000000,
            'may_amount' => 2000000,
            'jun_amount' => 2000000,
        ]);

        expect($line->getQuarterAmount(1))->toBe(3000000)
            ->and($line->getQuarterAmount(2))->toBe(6000000);
    });

    it('can get YTD budget', function () {
        $line = BudgetLine::factory()->create([
            'jan_amount' => 1000000,
            'feb_amount' => 2000000,
            'mar_amount' => 3000000,
        ]);

        expect($line->getYtdBudget(1))->toBe(1000000)
            ->and($line->getYtdBudget(2))->toBe(3000000)
            ->and($line->getYtdBudget(3))->toBe(6000000);
    });

    it('can distribute evenly', function () {
        $line = new BudgetLine;
        $line->distributeEvenly(12000000);

        expect($line->jan_amount)->toBe(1000000)
            ->and($line->dec_amount)->toBe(1000000)
            ->and($line->annual_amount)->toBe(12000000);
    });

    it('handles remainder when distributing', function () {
        $line = new BudgetLine;
        $line->distributeEvenly(12000010); // Not evenly divisible

        // 12000010 / 12 = 1000000 with remainder 10
        expect($line->jan_amount)->toBe(1000000)
            ->and($line->dec_amount)->toBe(1000010) // Remainder added to December
            ->and($line->annual_amount)->toBe(12000010);
    });
});
