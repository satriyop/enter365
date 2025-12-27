<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownPaymentApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $applicable = $this->applicable;
        $applicableData = null;

        if ($applicable instanceof Invoice) {
            $applicableData = [
                'type' => 'invoice',
                'id' => $applicable->id,
                'number' => $applicable->invoice_number,
                'total_amount' => $applicable->total_amount,
                'paid_amount' => $applicable->paid_amount,
            ];
        } elseif ($applicable instanceof Bill) {
            $applicableData = [
                'type' => 'bill',
                'id' => $applicable->id,
                'number' => $applicable->bill_number,
                'total_amount' => $applicable->total_amount,
                'paid_amount' => $applicable->paid_amount,
            ];
        }

        return [
            'id' => $this->id,
            'down_payment_id' => $this->down_payment_id,
            'applicable_type' => $this->applicable_type,
            'applicable_id' => $this->applicable_id,
            'applicable' => $applicableData,
            'amount' => $this->amount,
            'applied_date' => $this->applied_date->format('Y-m-d'),
            'notes' => $this->notes,
            'journal_entry_id' => $this->journal_entry_id,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
