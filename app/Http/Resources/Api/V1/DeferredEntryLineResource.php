<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\DeferredEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeferredEntryLine
 */
class DeferredEntryLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deferred_entry_id' => $this->deferred_entry_id,
            'sequence' => $this->sequence,
            'recognition_date' => $this->recognition_date?->toDateString(),
            'amount' => $this->amount,
            'remaining_amount' => $this->remaining_amount,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'posted_at' => $this->posted_at?->toIso8601String(),
        ];
    }
}
