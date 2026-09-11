<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AccountReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountReconciliation
 */
class AccountReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'partner_id' => $this->partner_id,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'reconciled_by' => $this->reconciled_by,
            'account' => $this->whenLoaded('account', fn () => $this->account === null ? null : [
                'id' => $this->account->id,
                'code' => $this->account->code,
                'name' => $this->account->name,
            ]),
            'partner' => $this->whenLoaded('partner', fn () => $this->partner === null ? null : [
                'id' => $this->partner->id,
                'code' => $this->partner->code,
                'name' => $this->partner->name,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'journal_entry_line_id' => $item->journal_entry_line_id,
                'amount' => $item->amount,
                'entry_number' => $item->journalEntryLine?->journalEntry?->entry_number,
                'debit' => $item->journalEntryLine?->debit,
                'credit' => $item->journalEntryLine?->credit,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
