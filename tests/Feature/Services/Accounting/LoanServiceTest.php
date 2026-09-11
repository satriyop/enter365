<?php

use App\Contracts\Accounting\LoanServiceInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

describe('LoanService', function () {
    it('builds an interest schedule whose principal lines sum to the loan principal', function () {
        $loan = Loan::factory()->create([
            'principal' => 1_200_000,
            'annual_interest_rate' => 12,
            'duration_months' => 12,
            'liability_account_id' => Account::query()->where('code', '2-2100')->value('id'),
            'interest_account_id' => Account::query()->where('code', '5-3001')->value('id'),
            'bank_account_id' => Account::query()->where('code', '1-1002')->value('id'),
        ]);

        $confirmed = app(LoanServiceInterface::class)->confirm($loan);

        expect($confirmed->lines)->toHaveCount(12)
            ->and($confirmed->lines->sum('principal_amount'))->toBe(1_200_000)
            ->and($confirmed->lines->last()->remaining_principal)->toBe(0)
            ->and($confirmed->lines->sum('interest_amount'))->toBeGreaterThan(0);
    });

    it('refuses to confirm an already running loan', function () {
        $loan = Loan::factory()->running()->create([
            'liability_account_id' => Account::query()->where('code', '2-2100')->value('id'),
            'interest_account_id' => Account::query()->where('code', '5-3001')->value('id'),
            'bank_account_id' => Account::query()->where('code', '1-1002')->value('id'),
        ]);

        expect(fn () => app(LoanServiceInterface::class)->confirm($loan))
            ->toThrow(BusinessRuleException::class, 'Hanya pinjaman draf yang bisa dikonfirmasi.');
    });

    it('puts the remainder of a zero-interest loan on the last installment', function () {
        $loan = Loan::factory()->create([
            'principal' => 1_000_000,
            'annual_interest_rate' => 0,
            'duration_months' => 3,
            'liability_account_id' => Account::query()->where('code', '2-2100')->value('id'),
            'interest_account_id' => Account::query()->where('code', '5-3001')->value('id'),
            'bank_account_id' => Account::query()->where('code', '1-1002')->value('id'),
        ]);

        $confirmed = app(LoanServiceInterface::class)->confirm($loan);

        expect($confirmed->lines->pluck('principal_amount')->all())->toBe([333_333, 333_333, 333_334])
            ->and($confirmed->lines->sum('principal_amount'))->toBe(1_000_000)
            ->and($confirmed->lines->sum('interest_amount'))->toBe(0);
    });
});
