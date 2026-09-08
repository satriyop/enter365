<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\JournalEntryLine
 */
class JournalEntryLineResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   journal_entry_id: int,
     *   account_id: int,
     *   partner_id: int|null,
     *   description: string|null,
     *   debit: int,
     *   credit: int,
     *   account?: AccountResource,
     *   partner?: ContactResource|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_entry_id' => $this->journal_entry_id,
            'account_id' => $this->account_id,
            'partner_id' => $this->partner_id,
            'analytic_distribution' => $this->analytic_distribution,
            'tax_tag_ids' => $this->tax_tag_ids,
            'description' => $this->description,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'account' => new AccountResource($this->whenLoaded('account')),
            'partner' => $this->whenLoaded('partner', fn () => $this->partner
                ? new ContactResource($this->partner)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
