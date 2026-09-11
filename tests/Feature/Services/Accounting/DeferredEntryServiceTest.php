<?php

use App\Contracts\Accounting\DeferredEntryServiceInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\DeferredEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

describe('DeferredEntryService', function () {
    it('puts the remainder of an uneven amount on the last recognition line', function () {
        $entry = DeferredEntry::factory()->expense()->create([
            'amount' => 1_000_000,
            'duration_months' => 3,
            'deferred_account_id' => Account::query()->where('code', '1-1501')->value('id'),
            'recognition_account_id' => Account::query()->where('code', '5-2100')->value('id'),
            'counterpart_account_id' => Account::query()->where('code', '1-1002')->value('id'),
        ]);

        $confirmed = app(DeferredEntryServiceInterface::class)->confirm($entry);

        expect($confirmed->lines->pluck('amount')->all())->toBe([333_333, 333_333, 333_334])
            ->and($confirmed->lines->sum('amount'))->toBe(1_000_000)
            ->and($confirmed->lines->last()->remaining_amount)->toBe(0);
    });

    it('refuses to confirm an already running deferred entry', function () {
        $entry = DeferredEntry::factory()->running()->create([
            'deferred_account_id' => Account::query()->where('code', '1-1501')->value('id'),
            'recognition_account_id' => Account::query()->where('code', '5-2100')->value('id'),
            'counterpart_account_id' => Account::query()->where('code', '1-1002')->value('id'),
        ]);

        expect(fn () => app(DeferredEntryServiceInterface::class)->confirm($entry))
            ->toThrow(BusinessRuleException::class, 'Hanya entri tangguhan draf yang bisa dikonfirmasi.');
    });
});
