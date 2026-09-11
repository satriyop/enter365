<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AccountingTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountingTransfer
 */
class AccountingTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'from_journal_id' => $this->from_journal_id,
            'to_journal_id' => $this->to_journal_id,
            'from_account_id' => $this->from_account_id,
            'to_account_id' => $this->to_account_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'memo' => $this->memo,
            'journal_entry_id' => $this->journal_entry_id,
            'from_journal' => $this->whenLoaded('fromJournal', fn () => $this->fromJournal === null ? null : [
                'id' => $this->fromJournal->id,
                'name' => $this->fromJournal->name,
                'type' => $this->fromJournal->type,
            ]),
            'to_journal' => $this->whenLoaded('toJournal', fn () => $this->toJournal === null ? null : [
                'id' => $this->toJournal->id,
                'name' => $this->toJournal->name,
                'type' => $this->toJournal->type,
            ]),
            'from_account' => $this->whenLoaded('fromAccount', fn () => $this->fromAccount === null ? null : [
                'id' => $this->fromAccount->id,
                'code' => $this->fromAccount->code,
                'name' => $this->fromAccount->name,
            ]),
            'to_account' => $this->whenLoaded('toAccount', fn () => $this->toAccount === null ? null : [
                'id' => $this->toAccount->id,
                'code' => $this->toAccount->code,
                'name' => $this->toAccount->name,
            ]),
            'journal_entry' => $this->whenLoaded('journalEntry', fn () => $this->journalEntry === null ? null : [
                'id' => $this->journalEntry->id,
                'entry_number' => $this->journalEntry->entry_number,
                'is_posted' => $this->journalEntry->is_posted,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
