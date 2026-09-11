<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

function loanAccounts(): array
{
    return [
        'liability' => Account::query()->where('code', '2-2100')->firstOrFail(),
        'interest' => Account::query()->where('code', '5-3001')->firstOrFail(),
        'bank' => Account::query()->where('code', '1-1002')->firstOrFail(),
    ];
}

describe('Loans (#143)', function () {
    it('creates lists and shows a loan', function () {
        $accounts = loanAccounts();

        $create = $this->postJson('/api/v1/loans', [
            'code' => 'LN-BCA-1',
            'name' => 'Kredit BCA',
            'principal' => 1_200_000,
            'annual_interest_rate' => 0,
            'duration_months' => 12,
            'start_date' => '2026-01-15',
            'liability_account_id' => $accounts['liability']->id,
            'interest_account_id' => $accounts['interest']->id,
            'bank_account_id' => $accounts['bank']->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'LN-BCA-1')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.principal', 1_200_000);

        $this->getJson('/api/v1/loans')->assertOk()
            ->assertJsonPath('data.0.code', 'LN-BCA-1');
    });

    it('confirms a zero-interest loan, posts disbursement, and builds equal principal lines', function () {
        $accounts = loanAccounts();
        $loan = Loan::factory()->create([
            'principal' => 1_200_000,
            'annual_interest_rate' => 0,
            'duration_months' => 3,
            'start_date' => '2026-01-10',
            'liability_account_id' => $accounts['liability']->id,
            'interest_account_id' => $accounts['interest']->id,
            'bank_account_id' => $accounts['bank']->id,
        ]);

        $confirm = $this->postJson("/api/v1/loans/{$loan->id}/confirm");

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.remaining_principal', 1_200_000)
            ->assertJsonCount(3, 'data.lines')
            ->assertJsonPath('data.lines.0.principal_amount', 400_000)
            ->assertJsonPath('data.lines.2.remaining_principal', 0)
            ->assertJsonPath('data.lines.0.due_date', '2026-01-31');

        expect(JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_LOAN)
            ->where('source_id', $loan->id)
            ->exists())->toBeTrue();

        $this->getJson('/api/v1/loans-analysis')
            ->assertOk()
            ->assertJsonPath('data.loan_count', 1)
            ->assertJsonPath('data.running_count', 1)
            ->assertJsonCount(3, 'data.upcoming');
    });

    it('posts the next installment and updates remaining principal', function () {
        $accounts = loanAccounts();
        $loan = Loan::factory()->create([
            'principal' => 1_200_000,
            'annual_interest_rate' => 0,
            'duration_months' => 3,
            'start_date' => now()->startOfMonth()->toDateString(),
            'liability_account_id' => $accounts['liability']->id,
            'interest_account_id' => $accounts['interest']->id,
            'bank_account_id' => $accounts['bank']->id,
        ]);

        $this->postJson("/api/v1/loans/{$loan->id}/confirm")->assertOk();
        $post = $this->postJson("/api/v1/loans/{$loan->id}/post-installment");

        $post->assertOk()
            ->assertJsonPath('data.remaining_principal', 800_000)
            ->assertJsonPath('data.lines.0.status', 'posted')
            ->assertJsonPath('data.status', 'running');
    });

    it('returns loans analysis totals', function () {
        $this->getJson('/api/v1/loans-analysis')->assertOk()
            ->assertJsonPath('data.loan_count', 0)
            ->assertJsonPath('data.remaining_principal', 0)
            ->assertJsonPath('data.upcoming', []);
    });

    it('validates required fields when creating a loan', function () {
        $this->postJson('/api/v1/loans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'principal', 'duration_months', 'start_date']);
    });

    it('refuses to update or delete a running loan', function () {
        $accounts = loanAccounts();
        $loan = Loan::factory()->create([
            'principal' => 1_200_000,
            'annual_interest_rate' => 0,
            'duration_months' => 2,
            'start_date' => '2026-01-10',
            'liability_account_id' => $accounts['liability']->id,
            'interest_account_id' => $accounts['interest']->id,
            'bank_account_id' => $accounts['bank']->id,
        ]);

        $this->postJson("/api/v1/loans/{$loan->id}/confirm")->assertOk();

        $this->putJson("/api/v1/loans/{$loan->id}", ['name' => 'Nope'])->assertConflict();
        $this->deleteJson("/api/v1/loans/{$loan->id}")->assertConflict();
    });

    it('closes the loan after the last installment is posted', function () {
        $accounts = loanAccounts();
        $loan = Loan::factory()->create([
            'principal' => 1_200_000,
            'annual_interest_rate' => 0,
            'duration_months' => 2,
            'start_date' => now()->startOfMonth()->toDateString(),
            'liability_account_id' => $accounts['liability']->id,
            'interest_account_id' => $accounts['interest']->id,
            'bank_account_id' => $accounts['bank']->id,
        ]);

        $this->postJson("/api/v1/loans/{$loan->id}/confirm")->assertOk();
        $this->postJson("/api/v1/loans/{$loan->id}/post-installment")->assertOk();
        $last = $this->postJson("/api/v1/loans/{$loan->id}/post-installment");

        $last->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.remaining_principal', 0);
    });
});
