<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   entry_number: string,
     *   entry_date: string|null,
     *   description: string,
     *   reference: string|null,
     *   source_type: string|null,
     *   source_id: int|null,
     *   fiscal_period_id: int,
     *   is_posted: bool,
     *   is_reversed: bool,
     *   total_debit: int,
     *   total_credit: int,
     *   is_balanced: bool,
     *   lines?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   fiscal_period?: array{id: int, name: string},
     *   reversed_by?: array{id: int, entry_number: string},
     *   reversal_of?: array{id: int, entry_number: string},
     *   created_by: int|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date?->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'fiscal_period_id' => $this->fiscal_period_id,
            'is_posted' => $this->is_posted,
            'is_reversed' => $this->is_reversed,
            'total_debit' => $this->getTotalDebit(),
            'total_credit' => $this->getTotalCredit(),
            'is_balanced' => $this->isBalanced(),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'fiscal_period' => new FiscalPeriodResource($this->whenLoaded('fiscalPeriod')),
            'reversed_by' => new JournalEntryResource($this->whenLoaded('reversedBy')),
            'reversal_of' => new JournalEntryResource($this->whenLoaded('reversalOf')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
