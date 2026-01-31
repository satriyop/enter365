<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use App\Models\User;
use App\Services\Accounting\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(BudgetService::class);
    $this->fiscalPeriod = FiscalPeriod::factory()->create();
    $this->revenueAccount = Account::factory()->create(['type' => Account::TYPE_REVENUE]);
    $this->expenseAccount = Account::factory()->create(['type' => Account::TYPE_EXPENSE]);
});

describe('BudgetService budget management', function () {
    it('creates budget with lines', function () {
        $budget = $this->service->createBudget([
            'name' => 'Budget 2024',
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ], [
            ['account_id' => $this->revenueAccount->id, 'annual_amount' => 120000000],
            ['account_id' => $this->expenseAccount->id, 'annual_amount' => 80000000],
        ]);

        expect($budget)->toBeInstanceOf(Budget::class);
        expect($budget->lines)->toHaveCount(2);
    });

    it('adds budget line with even distribution', function () {
        $budget = Budget::factory()->create(['fiscal_period_id' => $this->fiscalPeriod->id]);

        $line = $this->service->addBudgetLine($budget, [
            'account_id' => $this->revenueAccount->id,
            'annual_amount' => 1200000,
        ]);

        expect($line)->toBeInstanceOf(BudgetLine::class);
        expect($line->jan_amount)->toBe(100000); // 1200000 / 12
        expect($line->feb_amount)->toBe(100000);
    });

    it('updates budget line', function () {
        $budget = Budget::factory()->create();
        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->revenueAccount->id,
        ]);

        $updated = $this->service->updateBudgetLine($line, [
            'jan_amount' => 200000,
            'feb_amount' => 150000,
        ]);

        expect($updated->jan_amount)->toBe(200000);
        expect($updated->feb_amount)->toBe(150000);
    });
});

describe('BudgetService reports', function () {
    it('gets budget vs actual comparison', function () {
        $budget = Budget::factory()->create(['fiscal_period_id' => $this->fiscalPeriod->id]);
        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->revenueAccount->id,
            'annual_amount' => 1200000,
        ]);

        $comparison = $this->service->getBudgetVsActual($budget);

        expect($comparison)->toHaveCount(1);
        expect($comparison->first()->account_id)->toBe($this->revenueAccount->id);
    });

    it('gets budget summary', function () {
        $budget = Budget::factory()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'total_revenue' => 10000000,
            'total_expense' => 8000000,
        ]);

        $summary = $this->service->getBudgetSummary($budget);

        expect($summary)->toHaveKeys(['budget', 'annual', 'ytd']);
        expect($summary['annual']['budget_revenue'])->toBe(10000000);
        expect($summary['annual']['budget_expense'])->toBe(8000000);
    });

    it('copies budget to new fiscal period', function () {
        $budget = Budget::factory()->create(['fiscal_period_id' => $this->fiscalPeriod->id]);
        BudgetLine::factory()->count(2)->create(['budget_id' => $budget->id]);

        $newPeriod = FiscalPeriod::factory()->create();
        $copied = $this->service->copyBudget($budget, $newPeriod, 'Budget 2025');

        expect($copied->name)->toBe('Budget 2025');
        expect($copied->fiscal_period_id)->toBe($newPeriod->id);
        expect($copied->lines)->toHaveCount(2);
    });
});
