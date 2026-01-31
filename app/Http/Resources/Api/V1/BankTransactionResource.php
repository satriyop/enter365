<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\BankTransaction
 */
class BankTransactionResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   account_id: int,
     *   account?: AccountResource,
     *   transaction_date: string|null,
     *   description: string|null,
     *   reference: string|null,
     *   debit: int,
     *   credit: int,
     *   net_amount: int,
     *   balance: int,
     *   status: array{value: string, label: string, color: string, is_terminal: bool, is_editable: bool},
     *   is_reconciled: bool,
     *   matched_payment_id: int|null,
     *   matched_payment?: PaymentResource,
     *   matched_journal_line_id: int|null,
     *   reconciled_at: string|null,
     *   reconciled_by: int|null,
     *   import_batch: string|null,
     *   external_id: string|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => new AccountResource($this->whenLoaded('account')),
            'transaction_date' => $this->transaction_date?->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'net_amount' => $this->getNetAmount(),
            'balance' => $this->balance,
            'status' => new StatusResource($this->status),
            'is_reconciled' => $this->isReconciled(),
            'matched_payment_id' => $this->matched_payment_id,
            'matched_payment' => new PaymentResource($this->whenLoaded('matchedPayment')),
            'matched_journal_line_id' => $this->matched_journal_line_id,
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'reconciled_by' => $this->reconciled_by,
            'import_batch' => $this->import_batch,
            'external_id' => $this->external_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
