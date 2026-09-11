<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FixedAsset
 */
class FixedAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'asset_model_id' => $this->asset_model_id,
            'original_value' => $this->original_value,
            'salvage_value' => $this->salvage_value,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'method' => $this->method,
            'method_number' => $this->method_number,
            'method_period' => $this->method_period,
            'method_progress_factor' => $this->method_progress_factor !== null
                ? (float) $this->method_progress_factor
                : null,
            'asset_account_id' => $this->asset_account_id,
            'depreciation_account_id' => $this->depreciation_account_id,
            'expense_account_id' => $this->expense_account_id,
            'journal_id' => $this->journal_id,
            'status' => $this->status,
            'accumulated_depreciation' => $this->accumulated_depreciation,
            'book_value' => $this->bookValue(),
            'notes' => $this->notes,
            'asset_model' => $this->whenLoaded('assetModel', fn () => $this->assetModel === null ? null : [
                'id' => $this->assetModel->id,
                'code' => $this->assetModel->code,
                'name' => $this->assetModel->name,
            ]),
            'depreciation_lines' => AssetDepreciationLineResource::collection(
                $this->whenLoaded('depreciationLines')
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
