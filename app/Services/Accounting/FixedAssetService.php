<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\FixedAssetServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\AssetDepreciationLine;
use App\Models\Accounting\AssetModel;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use App\Services\Base\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class FixedAssetService extends BaseService implements FixedAssetServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalServiceInterface $journalService,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createModel(array $data): AssetModel
    {
        return $this->executeInTransaction('create_asset_model', function () use ($data) {
            return AssetModel::query()->create($data);
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateModel(AssetModel $model, array $data): AssetModel
    {
        return $this->executeInTransaction('update_asset_model', function () use ($model, $data) {
            $model->update($data);

            return $model->fresh() ?? $model;
        }, ['asset_model_id' => $model->id]);
    }

    public function deleteModel(AssetModel $model): void
    {
        $this->executeInTransaction('delete_asset_model', function () use ($model) {
            if ($model->assets()->exists()) {
                throw new BusinessRuleException(
                    'Model aset tidak bisa dihapus karena masih dipakai aset tetap.',
                    ['asset_model_id' => $model->id]
                );
            }

            $model->delete();
        }, ['asset_model_id' => $model->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAsset(array $data): FixedAsset
    {
        return $this->executeInTransaction('create_fixed_asset', function () use ($data) {
            $data = $this->applyModelDefaults($data);
            $this->assertDepreciable($data);
            $data['created_by'] = $this->getUserId();
            $data['status'] = FixedAsset::STATUS_DRAFT;
            $data['accumulated_depreciation'] = 0;

            return FixedAsset::query()->create(Arr::only($data, [
                'code',
                'name',
                'asset_model_id',
                'original_value',
                'salvage_value',
                'acquisition_date',
                'method',
                'method_number',
                'method_period',
                'method_progress_factor',
                'asset_account_id',
                'depreciation_account_id',
                'expense_account_id',
                'journal_id',
                'status',
                'accumulated_depreciation',
                'notes',
                'created_by',
            ]));
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAsset(FixedAsset $asset, array $data): FixedAsset
    {
        return $this->executeInTransaction('update_fixed_asset', function () use ($asset, $data) {
            if (! $asset->isDraft()) {
                throw new BusinessRuleException(
                    'Aset tetap yang sudah berjalan tidak bisa diubah.',
                    ['fixed_asset_id' => $asset->id]
                );
            }

            $data = $this->applyModelDefaults($data, $asset);
            $merged = array_merge($asset->only([
                'original_value',
                'salvage_value',
                'method_number',
            ]), $data);
            $this->assertDepreciable($merged);
            $asset->update($data);

            return $asset->fresh(['assetModel', 'depreciationLines']) ?? $asset;
        }, ['fixed_asset_id' => $asset->id]);
    }

    public function deleteAsset(FixedAsset $asset): void
    {
        $this->executeInTransaction('delete_fixed_asset', function () use ($asset) {
            if (! $asset->isDraft()) {
                throw new BusinessRuleException(
                    'Aset tetap yang sudah berjalan tidak bisa dihapus.',
                    ['fixed_asset_id' => $asset->id]
                );
            }

            $asset->depreciationLines()->delete();
            $asset->delete();
        }, ['fixed_asset_id' => $asset->id]);
    }

    public function confirm(FixedAsset $asset): FixedAsset
    {
        return $this->executeInTransaction('confirm_fixed_asset', function () use ($asset) {
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if (! $asset->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya aset draf yang bisa dikonfirmasi.',
                    ['fixed_asset_id' => $asset->id]
                );
            }

            $this->rebuildSchedule($asset);
            $asset->update(['status' => FixedAsset::STATUS_RUNNING]);

            return $asset->fresh(['depreciationLines', 'assetModel']) ?? $asset;
        }, ['fixed_asset_id' => $asset->id]);
    }

    public function postNextDepreciation(FixedAsset $asset): FixedAsset
    {
        return $this->executeInTransaction('post_asset_depreciation', function () use ($asset) {
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if (! $asset->isRunning()) {
                throw new BusinessRuleException(
                    'Hanya aset berjalan yang bisa diposting penyusutannya.',
                    ['fixed_asset_id' => $asset->id]
                );
            }

            $line = $asset->depreciationLines()
                ->where('status', AssetDepreciationLine::STATUS_DRAFT)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if ($line === null) {
                throw new BusinessRuleException(
                    'Tidak ada baris penyusutan yang menunggu posting.',
                    ['fixed_asset_id' => $asset->id]
                );
            }

            $entryPayload = [
                'entry_date' => Carbon::parse($line->depreciation_date)->toDateString(),
                'description' => 'Penyusutan '.$asset->code.' '.$asset->name,
                'reference' => $asset->code,
                'source_type' => JournalEntry::SOURCE_FIXED_ASSET,
                'source_id' => $asset->id,
                'lines' => [
                    [
                        'account_id' => $asset->expense_account_id,
                        'debit' => $line->amount,
                        'credit' => 0,
                        'description' => 'Beban penyusutan '.$asset->code,
                    ],
                    [
                        'account_id' => $asset->depreciation_account_id,
                        'debit' => 0,
                        'credit' => $line->amount,
                        'description' => 'Akumulasi penyusutan '.$asset->code,
                    ],
                ],
            ];
            if ($asset->journal_id) {
                $entryPayload['journal_id'] = $asset->journal_id;
            }
            $entry = $this->journalService->createEntry($entryPayload, true);

            $accumulated = (int) $asset->accumulated_depreciation + (int) $line->amount;
            $line->update([
                'status' => AssetDepreciationLine::STATUS_POSTED,
                'journal_entry_id' => $entry->id,
                'posted_at' => now(),
            ]);

            $updates = ['accumulated_depreciation' => $accumulated];
            if ($accumulated >= $asset->depreciableValue()) {
                $updates['status'] = FixedAsset::STATUS_CLOSED;
            }
            $asset->update($updates);

            return $asset->fresh(['depreciationLines', 'assetModel']) ?? $asset;
        }, ['fixed_asset_id' => $asset->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyModelDefaults(array $data, ?FixedAsset $asset = null): array
    {
        $modelId = $data['asset_model_id'] ?? $asset?->asset_model_id;
        if ($modelId === null) {
            return $data;
        }

        $model = AssetModel::query()->find((int) $modelId);
        if ($model === null) {
            return $data;
        }

        $defaults = [
            'method' => $model->method,
            'method_number' => $model->method_number,
            'method_period' => $model->method_period,
            'method_progress_factor' => $model->method_progress_factor,
            'asset_account_id' => $model->asset_account_id,
            'depreciation_account_id' => $model->depreciation_account_id,
            'expense_account_id' => $model->expense_account_id,
            'journal_id' => $model->journal_id,
        ];

        if (! array_key_exists('salvage_value', $data) && isset($data['original_value'])) {
            $percent = (float) $model->salvage_value_percent;
            $defaults['salvage_value'] = (int) round(((int) $data['original_value']) * $percent / 100);
        }

        return $data + $defaults;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDepreciable(array $data): void
    {
        $original = (int) ($data['original_value'] ?? 0);
        $salvage = (int) ($data['salvage_value'] ?? 0);
        $periods = (int) ($data['method_number'] ?? 0);

        if ($original <= 0) {
            throw new BusinessRuleException('Nilai perolehan aset harus lebih dari 0.');
        }
        if ($salvage < 0 || $salvage >= $original) {
            throw new BusinessRuleException('Nilai sisa harus lebih kecil dari nilai perolehan.');
        }
        if ($periods < 1) {
            throw new BusinessRuleException('Jumlah periode penyusutan minimal 1.');
        }
    }

    private function rebuildSchedule(FixedAsset $asset): void
    {
        $asset->depreciationLines()->delete();

        $depreciable = $asset->depreciableValue();
        $periods = (int) $asset->method_number;
        $lines = $asset->method === AssetModel::METHOD_DEGRESSIVE
            ? $this->degressiveAmounts($asset, $depreciable, $periods)
            : $this->linearAmounts($depreciable, $periods);

        $date = Carbon::parse($asset->acquisition_date)->endOfMonth();
        $accumulated = 0;

        foreach ($lines as $index => $amount) {
            $accumulated += $amount;
            $asset->depreciationLines()->create([
                'sequence' => $index + 1,
                'depreciation_date' => $date->toDateString(),
                'amount' => $amount,
                'depreciated_value' => $accumulated,
                'remaining_value' => (int) $asset->original_value - $accumulated,
                'status' => AssetDepreciationLine::STATUS_DRAFT,
            ]);

            $date = $asset->method_period === AssetModel::PERIOD_YEAR
                ? $date->copy()->addYear()->endOfMonth()
                : $date->copy()->addMonthNoOverflow()->endOfMonth();
        }
    }

    /**
     * @return list<int>
     */
    private function linearAmounts(int $depreciable, int $periods): array
    {
        $base = intdiv($depreciable, $periods);
        $remainder = $depreciable % $periods;
        $lines = [];

        for ($i = 1; $i <= $periods; $i++) {
            $lines[] = $base + ($i === $periods ? $remainder : 0);
        }

        return $lines;
    }

    /**
     * @return list<int>
     */
    private function degressiveAmounts(FixedAsset $asset, int $depreciable, int $periods): array
    {
        $factor = (float) ($asset->method_progress_factor ?? 2);
        $rate = $factor / $periods;
        $remaining = $depreciable;
        $lines = [];

        for ($i = 1; $i <= $periods; $i++) {
            if ($i === $periods) {
                $lines[] = $remaining;

                break;
            }

            $amount = (int) round($remaining * $rate);
            $amount = max(0, min($amount, $remaining));
            $lines[] = $amount;
            $remaining -= $amount;
        }

        return $lines;
    }
}
