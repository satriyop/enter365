<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\EmployeeExpense;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    $this->user = authenticatedAdmin();
    $this->expenseAccount = Account::query()->where('code', '5-2100')->firstOrFail();
});

describe('Employee expenses (#153)', function () {
    it('creates a draft employee expense', function () {
        $response = $this->postJson('/api/v1/employee-expenses', [
            'employee_id' => $this->user->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Taxi to client',
            'amount' => 150_000,
            'tax_amount' => 16_500,
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.amount', 150_000)
            ->assertJsonPath('data.total_amount', 166_500);

        expect($response->json('data.expense_number'))->toStartWith('EXP-');
    });

    it('submits approves and posts an employee expense to the ledger', function () {
        $create = $this->postJson('/api/v1/employee-expenses', [
            'employee_id' => $this->user->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Hotel',
            'amount' => 200_000,
            'expense_account_id' => $this->expenseAccount->id,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->postJson("/api/v1/employee-expenses/{$id}/post")->assertStatus(409);

        $this->postJson("/api/v1/employee-expenses/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->postJson("/api/v1/employee-expenses/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $posted = $this->postJson("/api/v1/employee-expenses/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $entry = JournalEntry::query()->findOrFail($posted->json('data.journal_entry_id'));
        expect($entry->is_posted)->toBeTrue()
            ->and($entry->isBalanced())->toBeTrue()
            ->and($entry->source_type)->toBe(JournalEntry::SOURCE_EMPLOYEE_EXPENSE)
            ->and((int) $entry->lines()->sum('debit'))->toBe(200_000);
    });

    it('refuses a submitted expense and cancels a posted one with a reversing journal', function () {
        $refused = $this->postJson('/api/v1/employee-expenses', [
            'employee_id' => $this->user->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Snacks',
            'amount' => 25_000,
            'expense_account_id' => $this->expenseAccount->id,
        ])->json('data.id');

        $this->postJson("/api/v1/employee-expenses/{$refused}/submit")->assertOk();
        $this->postJson("/api/v1/employee-expenses/{$refused}/refuse", [
            'reason' => 'Bukan biaya dinas.',
        ])->assertOk()->assertJsonPath('data.status', 'refused');

        $postedId = $this->postJson('/api/v1/employee-expenses', [
            'employee_id' => $this->user->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Fuel',
            'amount' => 80_000,
            'expense_account_id' => $this->expenseAccount->id,
        ])->json('data.id');

        $this->postJson("/api/v1/employee-expenses/{$postedId}/submit")->assertOk();
        $this->postJson("/api/v1/employee-expenses/{$postedId}/approve")->assertOk();
        $this->postJson("/api/v1/employee-expenses/{$postedId}/post")->assertOk();

        $this->postJson("/api/v1/employee-expenses/{$postedId}/cancel", [
            'reason' => 'Salah akun.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $expense = EmployeeExpense::query()->findOrFail($postedId);
        $original = JournalEntry::query()->findOrFail($expense->journal_entry_id);
        $reversal = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_REVERSAL)
            ->where('source_id', $original->id)
            ->first();

        expect($reversal)->not->toBeNull()
            ->and($reversal->isBalanced())->toBeTrue();
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/employee-expenses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'expense_date', 'description', 'amount', 'expense_account_id']);
    });
});
