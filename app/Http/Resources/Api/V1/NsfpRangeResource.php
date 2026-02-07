<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tax\NsfpRange
 */
class NsfpRangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_code' => $this->transaction_code,
            'branch_code' => $this->branch_code,
            'year_code' => $this->year_code,
            'range_start' => $this->range_start,
            'range_end' => $this->range_end,
            'next_number' => $this->next_number,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'description' => $this->description,

            // Computed fields
            'capacity' => $this->getCapacity(),
            'remaining' => $this->getRemainingCount(),
            'is_exhausted' => $this->isExhausted(),
            'is_low_stock' => $this->isLowStock(),
            'utilization_percent' => $this->getCapacity() > 0
                ? round(($this->used_count / $this->getCapacity()) * 100, 1)
                : 0,

            // Formatted range display
            'formatted_range_start' => $this->formatNumber($this->range_start),
            'formatted_range_end' => $this->formatNumber($this->range_end),
            'formatted_next_number' => ! $this->isExhausted()
                ? $this->formatNumber($this->next_number)
                : null,

            // Relationships
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('createdBy')),
            'invoice_count' => $this->whenCounted('invoices', fn () => $this->invoices_count),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
