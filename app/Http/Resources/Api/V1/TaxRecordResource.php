<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Tax\TaxRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tax\TaxRecord
 */
class TaxRecordResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, rate: float, computation: string, applicability: string, is_active: bool, invoice_account_id: int|null, refund_account_id: int|null, tax_tag_id: int|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'rate' => (float) $this->rate,
            'computation' => $this->computation ?? TaxRecord::COMPUTATION_PERCENTAGE,
            'applicability' => $this->applicability,
            'is_active' => $this->is_active,
            'invoice_account_id' => $this->invoice_account_id,
            'refund_account_id' => $this->refund_account_id,
            'tax_tag_id' => $this->tax_tag_id,
        ];
    }
}
