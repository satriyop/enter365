<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AssetModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetModel
 */
class AssetModelResource extends JsonResource
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
            'method' => $this->method,
            'method_number' => $this->method_number,
            'method_period' => $this->method_period,
            'method_progress_factor' => $this->method_progress_factor !== null
                ? (float) $this->method_progress_factor
                : null,
            'salvage_value_percent' => (float) $this->salvage_value_percent,
            'asset_account_id' => $this->asset_account_id,
            'depreciation_account_id' => $this->depreciation_account_id,
            'expense_account_id' => $this->expense_account_id,
            'journal_id' => $this->journal_id,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'asset_account' => $this->whenLoaded('assetAccount', fn () => [
                'id' => $this->assetAccount?->id,
                'code' => $this->assetAccount?->code,
                'name' => $this->assetAccount?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
