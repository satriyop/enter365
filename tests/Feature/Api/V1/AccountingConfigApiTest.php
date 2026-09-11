<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingLedger;
use App\Models\Accounting\CashRounding;
use App\Models\Accounting\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    authenticatedAdmin();
});

describe('Accounting config masters (#151)', function () {
    it('lists seeded currencies and creates a new one', function () {
        $this->getJson('/api/v1/currencies')->assertOk()
            ->assertJsonPath('data.0.code', 'IDR');

        $this->postJson('/api/v1/currencies', [
            'code' => 'gbp',
            'name' => 'British Pound',
            'symbol' => '£',
            'decimal_places' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'GBP')
            ->assertJsonPath('data.is_base_currency', false);
    });

    it('refuses to delete the base currency', function () {
        $idr = Currency::query()->where('code', 'IDR')->firstOrFail();

        $this->deleteJson('/api/v1/currencies/'.$idr->id)->assertConflict();
    });

    it('creates lists and applies a cash rounding rule', function () {
        $profit = Account::query()->where('code', '4-2002')->first()
            ?? Account::query()->where('type', 'revenue')->firstOrFail();
        $loss = Account::query()->where('code', '5-2911')->first()
            ?? Account::query()->where('type', 'expense')->firstOrFail();

        $create = $this->postJson('/api/v1/cash-roundings', [
            'name' => 'Nearest Rp 100',
            'rounding' => 100,
            'strategy' => 'half_up',
            'profit_account_id' => $profit->id,
            'loss_account_id' => $loss->id,
        ]);

        $create->assertCreated()->assertJsonPath('data.rounding', 100);

        $rounding = CashRounding::query()->findOrFail($create->json('data.id'));
        expect($rounding->roundAmount(9_240))->toBe(9_200)
            ->and($rounding->roundAmount(9_250))->toBe(9_300);

        $this->getJson('/api/v1/cash-roundings')->assertOk()
            ->assertJsonPath('data.0.name', 'Nearest Rp 100');
    });

    it('validates cash rounding strategy', function () {
        $this->postJson('/api/v1/cash-roundings', [
            'name' => 'Bad',
            'rounding' => 100,
            'strategy' => 'magic',
        ])->assertUnprocessable()->assertJsonValidationErrors(['strategy']);
    });

    it('lists the statutory ledger and creates an extra book', function () {
        $this->getJson('/api/v1/accounting-ledgers')->assertOk()
            ->assertJsonPath('data.0.code', 'STATUTORY')
            ->assertJsonPath('data.0.is_default', true);

        $this->postJson('/api/v1/accounting-ledgers', [
            'code' => 'ifrs',
            'name' => 'IFRS',
            'currency_code' => 'IDR',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'IFRS')
            ->assertJsonPath('data.is_default', false);
    });

    it('refuses to delete the default ledger', function () {
        $ledger = AccountingLedger::query()->where('is_default', true)->firstOrFail();

        $this->deleteJson('/api/v1/accounting-ledgers/'.$ledger->id)->assertConflict();
    });
});
