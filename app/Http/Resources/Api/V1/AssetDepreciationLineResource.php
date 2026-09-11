<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AssetDepreciationLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetDepreciationLine
 */
class AssetDepreciationLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fixed_asset_id' => $this->fixed_asset_id,
            'sequence' => $this->sequence,
            'depreciation_date' => $this->depreciation_date?->toDateString(),
            'amount' => $this->amount,
            'depreciated_value' => $this->depreciated_value,
            'remaining_value' => $this->remaining_value,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'asset' => $this->whenLoaded('asset', fn () => [
                'id' => $this->asset?->id,
                'code' => $this->asset?->code,
                'name' => $this->asset?->name,
            ]),
        ];
    }
}
