<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $item */
        $item = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'id' => $item['id'] ?? null,
            'journal_entry_line_id' => $item['journal_entry_line_id'] ?? null,
            'journal_entry_id' => $item['journal_entry_id'] ?? null,
            'entry_number' => $item['entry_number'] ?? null,
            'entry_date' => $item['entry_date'] ?? null,
            'analytic_account_id' => $item['analytic_account_id'] ?? null,
            'account_id' => $item['account_id'] ?? null,
            'partner_id' => $item['partner_id'] ?? null,
            'percentage' => $item['percentage'] ?? 0,
            'amount' => $item['amount'] ?? 0,
            'description' => $item['description'] ?? null,
            'analytic_account' => $item['analytic_account'] ?? null,
            'account' => $item['account'] ?? null,
            'partner' => $item['partner'] ?? null,
        ];
    }
}
