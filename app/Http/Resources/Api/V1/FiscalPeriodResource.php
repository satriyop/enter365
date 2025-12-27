<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\FiscalPeriod
 */
class FiscalPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_closed' => $this->is_closed,
            'is_locked' => $this->is_locked,
            'is_open' => $this->isOpen(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->closed_by,
            'closing_entry_id' => $this->closing_entry_id,
            'retained_earnings_amount' => $this->retained_earnings_amount,
            'closing_notes' => $this->closing_notes,
            'closing_entry' => new JournalEntryResource($this->whenLoaded('closingEntry')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
