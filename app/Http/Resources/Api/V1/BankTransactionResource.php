<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\BankTransaction
 */
class BankTransactionResource extends JsonResource
{
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
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
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

    protected function getStatusLabel(): string
    {
        return match ($this->status) {
            'unmatched' => 'Belum Di-match',
            'matched' => 'Sudah Di-match',
            'reconciled' => 'Sudah Rekonsiliasi',
            default => $this->status,
        };
    }
}
