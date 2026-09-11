<?php

use App\Contracts\Accounting\FixedAssetServiceInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\AssetModel;
use App\Models\Accounting\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

describe('FixedAssetService', function () {
    it('puts remainder sen on the last linear period', function () {
        $asset = FixedAsset::factory()->create([
            'original_value' => 100_000,
            'salvage_value' => 1,
            'method_number' => 3,
            'asset_account_id' => Account::query()->where('code', '1-2200')->value('id'),
            'depreciation_account_id' => Account::query()->where('code', '1-2201')->value('id'),
            'expense_account_id' => Account::query()->where('code', '5-2500')->value('id'),
        ]);

        $confirmed = app(FixedAssetServiceInterface::class)->confirm($asset);
        $amounts = $confirmed->depreciationLines->pluck('amount');

        expect($amounts->all())->toBe([33333, 33333, 33333])
            ->and($amounts->sum())->toBe(99_999)
            ->and($confirmed->depreciationLines->last()->remaining_value)->toBe(1);
    });

    it('refuses to confirm an already running asset', function () {
        $asset = FixedAsset::factory()->running()->create([
            'asset_account_id' => Account::query()->where('code', '1-2200')->value('id'),
            'depreciation_account_id' => Account::query()->where('code', '1-2201')->value('id'),
            'expense_account_id' => Account::query()->where('code', '5-2500')->value('id'),
        ]);

        expect(fn () => app(FixedAssetServiceInterface::class)->confirm($asset))
            ->toThrow(BusinessRuleException::class, 'Hanya aset draf yang bisa dikonfirmasi.');
    });

    it('refuses to delete a model assigned to assets', function () {
        $model = AssetModel::factory()->create([
            'asset_account_id' => Account::query()->where('code', '1-2200')->value('id'),
            'depreciation_account_id' => Account::query()->where('code', '1-2201')->value('id'),
            'expense_account_id' => Account::query()->where('code', '5-2500')->value('id'),
        ]);
        FixedAsset::factory()->create([
            'asset_model_id' => $model->id,
            'asset_account_id' => $model->asset_account_id,
            'depreciation_account_id' => $model->depreciation_account_id,
            'expense_account_id' => $model->expense_account_id,
        ]);

        expect(fn () => app(FixedAssetServiceInterface::class)->deleteModel($model))
            ->toThrow(BusinessRuleException::class);
    });
});
