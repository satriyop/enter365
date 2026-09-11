<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\DeferredEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeferredEntry
 */
class DeferredEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'code' => $this->code,
            'name' => $this->name,
            'contact_id' => $this->contact_id,
            'amount' => $this->amount,
            'duration_months' => $this->duration_months,
            'start_date' => $this->start_date?->toDateString(),
            'deferred_account_id' => $this->deferred_account_id,
            'recognition_account_id' => $this->recognition_account_id,
            'counterpart_account_id' => $this->counterpart_account_id,
            'journal_id' => $this->journal_id,
            'status' => $this->status,
            'remaining_amount' => $this->remaining_amount,
            'origination_journal_entry_id' => $this->origination_journal_entry_id,
            'notes' => $this->notes,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'code' => $this->contact->code,
                'name' => $this->contact->name,
            ]),
            'lines' => DeferredEntryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
