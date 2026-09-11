<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\FiscalPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalPosition
 */
class FiscalPositionResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   code: string,
     *   name: string,
     *   notes: string|null,
     *   is_active: bool,
     *   tax_maps: list<array{id: int, source_tax_record_id: int, dest_tax_record_id: int|null, source_tax: array{id: int, code: string, name: string, rate: float}|null, dest_tax: array{id: int, code: string, name: string, rate: float}|null}>,
     *   account_maps: list<array{id: int, source_account_id: int, dest_account_id: int, source_account: array{id: int, code: string, name: string}|null, dest_account: array{id: int, code: string, name: string}|null}>,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'tax_maps' => $this->whenLoaded('taxMaps', fn () => $this->taxMaps->map(fn ($map) => [
                'id' => $map->id,
                'source_tax_record_id' => $map->source_tax_record_id,
                'dest_tax_record_id' => $map->dest_tax_record_id,
                'source_tax' => $map->relationLoaded('sourceTax') && $map->sourceTax
                    ? [
                        'id' => $map->sourceTax->id,
                        'code' => $map->sourceTax->code,
                        'name' => $map->sourceTax->name,
                        'rate' => (float) $map->sourceTax->rate,
                    ]
                    : null,
                'dest_tax' => $map->relationLoaded('destTax') && $map->destTax
                    ? [
                        'id' => $map->destTax->id,
                        'code' => $map->destTax->code,
                        'name' => $map->destTax->name,
                        'rate' => (float) $map->destTax->rate,
                    ]
                    : null,
            ])->values()->all(), []),
            'account_maps' => $this->whenLoaded('accountMaps', fn () => $this->accountMaps->map(fn ($map) => [
                'id' => $map->id,
                'source_account_id' => $map->source_account_id,
                'dest_account_id' => $map->dest_account_id,
                'source_account' => $map->relationLoaded('sourceAccount') && $map->sourceAccount
                    ? [
                        'id' => $map->sourceAccount->id,
                        'code' => $map->sourceAccount->code,
                        'name' => $map->sourceAccount->name,
                    ]
                    : null,
                'dest_account' => $map->relationLoaded('destAccount') && $map->destAccount
                    ? [
                        'id' => $map->destAccount->id,
                        'code' => $map->destAccount->code,
                        'name' => $map->destAccount->name,
                    ]
                    : null,
            ])->values()->all(), []),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
