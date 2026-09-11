<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconcileLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $line */
        $line = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => $line['id'] ?? null,
            'journal_entry_id' => $line['journal_entry_id'] ?? null,
            'account_id' => $line['account_id'] ?? null,
            'partner_id' => $line['partner_id'] ?? null,
            'entry_number' => $line['entry_number'] ?? null,
            'entry_date' => $line['entry_date'] ?? null,
            'description' => $line['description'] ?? null,
            'debit' => $line['debit'] ?? 0,
            'credit' => $line['credit'] ?? 0,
            'reconciled_amount' => $line['reconciled_amount'] ?? 0,
            'residual' => $line['residual'] ?? 0,
            'side' => $line['side'] ?? null,
            'partner' => $line['partner'] ?? null,
        ];
    }
}
