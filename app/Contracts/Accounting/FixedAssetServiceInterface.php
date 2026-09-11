<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\AssetModel;
use App\Models\Accounting\FixedAsset;

interface FixedAssetServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createModel(array $data): AssetModel;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateModel(AssetModel $model, array $data): AssetModel;

    public function deleteModel(AssetModel $model): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAsset(array $data): FixedAsset;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAsset(FixedAsset $asset, array $data): FixedAsset;

    public function deleteAsset(FixedAsset $asset): void;

    public function confirm(FixedAsset $asset): FixedAsset;

    public function postNextDepreciation(FixedAsset $asset): FixedAsset;
}
