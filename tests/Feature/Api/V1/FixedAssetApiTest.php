<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetModel;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

function fixedAssetAccounts(): array
{
    return [
        'asset' => Account::query()->where('code', '1-2200')->firstOrFail(),
        'accum' => Account::query()->where('code', '1-2201')->firstOrFail(),
        'expense' => Account::query()->where('code', '5-2500')->firstOrFail(),
    ];
}

describe('Asset models (#142)', function () {
    it('creates lists and shows an asset model', function () {
        $accounts = fixedAssetAccounts();

        $create = $this->postJson('/api/v1/asset-models', [
            'code' => 'VEHICLE-5Y',
            'name' => 'Kendaraan 5 tahun',
            'method' => 'linear',
            'method_number' => 60,
            'method_period' => 'month',
            'asset_account_id' => $accounts['asset']->id,
            'depreciation_account_id' => $accounts['accum']->id,
            'expense_account_id' => $accounts['expense']->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'VEHICLE-5Y')
            ->assertJsonPath('data.method_number', 60);

        $this->getJson('/api/v1/asset-models')->assertOk()
            ->assertJsonPath('data.0.code', 'VEHICLE-5Y');
    });

    it('refuses to delete a model still used by an asset', function () {
        $accounts = fixedAssetAccounts();
        $model = AssetModel::factory()->create([
            'asset_account_id' => $accounts['asset']->id,
            'depreciation_account_id' => $accounts['accum']->id,
            'expense_account_id' => $accounts['expense']->id,
        ]);
        FixedAsset::factory()->create([
            'asset_model_id' => $model->id,
            'asset_account_id' => $accounts['asset']->id,
            'depreciation_account_id' => $accounts['accum']->id,
            'expense_account_id' => $accounts['expense']->id,
        ]);

        $this->deleteJson("/api/v1/asset-models/{$model->id}")->assertConflict();
    });
});

describe('Fixed assets and depreciation schedule (#142)', function () {
    it('creates an asset from a model and copies method and accounts', function () {
        $accounts = fixedAssetAccounts();
        $model = AssetModel::factory()->create([
            'code' => 'OFFICE-4Y',
            'method_number' => 48,
            'salvage_value_percent' => 10,
            'asset_account_id' => $accounts['asset']->id,
            'depreciation_account_id' => $accounts['accum']->id,
            'expense_account_id' => $accounts['expense']->id,
        ]);

        $create = $this->postJson('/api/v1/assets', [
            'code' => 'FA-001',
            'name' => 'Laptop kantor',
            'asset_model_id' => $model->id,
            'original_value' => 10_000_000,
            'acquisition_date' => '2026-01-15',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'FA-001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.method_number', 48)
            ->assertJsonPath('data.asset_account_id', $accounts['asset']->id)
            ->assertJsonPath('data.salvage_value', 1_000_000)
            ->assertJsonPath('data.book_value', 10_000_000);
    });

    it('confirms a draft asset and builds a linear schedule that sums to depreciable value', function () {
        $accounts = fixedAssetAccounts();
        $asset = FixedAsset::factory()->create([
            'original_value' => 1_000_000,
            'salvage_value' => 100_000,
            'method_number' => 3,
            'acquisition_date' => '2026-01-10',
            'asset_account_id' => $accounts['asset']->id,
            'depreciation_account_id' => $accounts['accum']->id,
            'expense_account_id' => $accounts['expense']->id,
        ]);

        $confirm = $this->postJson("/api/v1/assets/{$asset->id}/confirm");

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonCount(3, 'data.depreciation_lines');

        $amounts = collect($confirm->json('data.depreciation_lines'))->pluck('amount');
        expect($amounts->sum())->toBe(900_000)
            ->and($confirm->json('data.depreciation_lines.0.depreciation_date'))->toBe('2026-01-31')
            ->and($confirm->json('data.depreciation_lines.2.remaining_value'))->toBe(100_000);
    });

    it('posts the next depreciation line to a balanced journal entry', function () {
        $accounts = fixedAssetAccounts();
        $asset = FixedAsset::factory()->create([
            'original_value' => 1_200_000,
            'salvage_value' => 0,
            'method_number' => 3,
            'acquisition_date' => now()->startOfMonth()->toDateString(),
            'asset_account_id' => $accounts['asset']->id,
            'depreciation_account_id' => $accounts['accum']->id,
            'expense_account_id' => $accounts['expense']->id,
        ]);

        $this->postJson("/api/v1/assets/{$asset->id}/confirm")->assertOk();
        $post = $this->postJson("/api/v1/assets/{$asset->id}/post-depreciation");

        $post->assertOk()
            ->assertJsonPath('data.accumulated_depreciation', 400_000)
            ->assertJsonPath('data.book_value', 800_000)
            ->assertJsonPath('data.depreciation_lines.0.status', 'posted');

        $entry = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_FIXED_ASSET)
            ->where('source_id', $asset->id)
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->is_posted)->toBeTrue();

        $this->getJson('/api/v1/depreciation-schedule')->assertOk()
            ->assertJsonPath('data.0.fixed_asset_id', $asset->id);
    });

    it('is a different route from fiscal periods', function () {
        $this->getJson('/api/v1/assets')->assertOk();
        $this->getJson('/api/v1/asset-models')->assertOk();
        $this->getJson('/api/v1/depreciation-schedule')->assertOk();
        $this->getJson('/api/v1/fiscal-periods')->assertOk();
    });
});
