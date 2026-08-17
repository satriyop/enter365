<?php

declare(strict_types=1);

use App\Contracts\Accounting\BudgetServiceInterface;
use App\Enums\BudgetStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(BudgetServiceInterface::class);
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

        $updated = $this->service->updateBudgetLine($budget, $line, [
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

    it('refuses to copy when the target period already has a budget', function () {
        $budget = Budget::factory()->create(['fiscal_period_id' => $this->fiscalPeriod->id]);
        $existingPeriod = FiscalPeriod::factory()->create();
        Budget::factory()->forPeriod($existingPeriod)->create();

        expect(fn () => $this->service->copyBudget($budget, $existingPeriod))
            ->toThrow(BusinessRuleException::class, 'Sudah ada anggaran untuk periode ini.');
    });
});

describe('BudgetService lifecycle', function () {
    it('approves a draft budget with lines and records the approver', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->expenseAccount->id,
        ]);

        $approved = $this->service->approve($budget);

        expect($approved->status)->toBe(BudgetStatus::Approved)
            ->and($approved->approved_by)->toBe($this->user->id)
            ->and($approved->approved_at)->not->toBeNull();
    });

    it('refuses to approve a budget without lines', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->approve($budget))
            ->toThrow(BusinessRuleException::class, 'Anggaran harus memiliki minimal satu baris.');
    });

    it('refuses to approve a budget that is not draft', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->approve($budget))
            ->toThrow(BusinessRuleException::class, 'Anggaran ini sudah disetujui atau ditutup.');
    });

    it('reopens an approved budget back to draft', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'approved_by' => $this->user->id,
        ]);

        $reopened = $this->service->reopen($budget);

        expect($reopened->status)->toBe(BudgetStatus::Draft)
            ->and($reopened->approved_by)->toBeNull()
            ->and($reopened->approved_at)->toBeNull();
    });

    it('refuses to reopen a closed budget', function () {
        $budget = Budget::factory()->closed()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->reopen($budget))
            ->toThrow(BusinessRuleException::class, 'Anggaran yang sudah ditutup tidak bisa dibuka kembali.');
    });

    it('closes an approved budget', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        $closed = $this->service->close($budget);

        expect($closed->status)->toBe(BudgetStatus::Closed);
    });

    it('refuses to close a draft budget', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->close($budget))
            ->toThrow(BusinessRuleException::class, 'Hanya anggaran yang sudah disetujui yang bisa ditutup.');
    });

    it('deletes a draft budget', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->expenseAccount->id,
        ]);

        $this->service->deleteBudget($budget);

        expect(Budget::query()->find($budget->id))->toBeNull();
    });

    it('refuses to delete an approved budget', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->deleteBudget($budget))
            ->toThrow(BusinessRuleException::class, 'Anggaran yang sudah disetujui atau ditutup tidak bisa dihapus.');
    });

    it('refuses to add a line to an approved budget', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->addBudgetLine($budget, [
            'account_id' => $this->expenseAccount->id,
            'annual_amount' => 1200000,
        ]))->toThrow(BusinessRuleException::class, 'Anggaran yang sudah disetujui tidak bisa diubah.');
    });

    it('refuses to update a line on an approved budget', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->expenseAccount->id,
        ]);

        expect(fn () => $this->service->updateBudgetLine($budget, $line, ['jan_amount' => 1]))
            ->toThrow(BusinessRuleException::class, 'Anggaran yang sudah disetujui tidak bisa diubah.');
    });

    it('refuses to delete a line on an approved budget', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->expenseAccount->id,
        ]);

        expect(fn () => $this->service->deleteBudgetLine($budget, $line))
            ->toThrow(BusinessRuleException::class, 'Anggaran yang sudah disetujui tidak bisa diubah.');
    });

    it('refuses a duplicate account on the same budget', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $this->expenseAccount->id,
        ]);

        expect(fn () => $this->service->addBudgetLine($budget, [
            'account_id' => $this->expenseAccount->id,
            'annual_amount' => 500000,
        ]))->toThrow(BusinessRuleException::class, 'Akun sudah ada dalam anggaran ini.');
    });

    it('deletes a line from a draft budget and recalculates totals', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'total_expense' => 0,
        ]);
        $line = $this->service->addBudgetLine($budget, [
            'account_id' => $this->expenseAccount->id,
            'annual_amount' => 1200000,
        ]);
        $budget->recalculateTotals();

        $this->service->deleteBudgetLine($budget, $line);

        expect(BudgetLine::query()->find($line->id))->toBeNull()
            ->and((int) $budget->fresh()->total_expense)->toBe(0);
    });

    it('updates a draft budget header', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'name' => 'Lama',
        ]);

        $updated = $this->service->updateBudget($budget, ['name' => 'Baru']);

        expect($updated->name)->toBe('Baru');
    });

    it('refuses to update an approved budget header', function () {
        $budget = Budget::factory()->approved()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        expect(fn () => $this->service->updateBudget($budget, ['name' => 'Baru']))
            ->toThrow(BusinessRuleException::class, 'Anggaran yang sudah disetujui atau ditutup tidak bisa diubah.');
    });

    it('does not let updateBudget change status', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);

        $updated = $this->service->updateBudget($budget, [
            'name' => 'Header saja',
            'status' => BudgetStatus::Approved->value,
        ]);

        expect($updated->status)->toBe(BudgetStatus::Draft)
            ->and($updated->name)->toBe('Header saja');
    });

    it('refuses a line that does not belong to the budget', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        $other = BudgetLine::factory()->create();

        expect(fn () => $this->service->updateBudgetLine($budget, $other, ['jan_amount' => 1]))
            ->toThrow(EntityNotFoundException::class);
    });

    it('refuses to delete a line that does not belong to the budget', function () {
        $budget = Budget::factory()->draft()->create([
            'fiscal_period_id' => $this->fiscalPeriod->id,
        ]);
        $other = BudgetLine::factory()->create();

        expect(fn () => $this->service->deleteBudgetLine($budget, $other))
            ->toThrow(EntityNotFoundException::class);
    });
});
