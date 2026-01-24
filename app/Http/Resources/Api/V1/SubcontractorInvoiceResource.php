<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shared\SubcontractorInvoice
 */
class SubcontractorInvoiceResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   invoice_number: string,
     *   subcontractor_id: int,
     *   subcontractor?: array{id: int, name: string},
     *   subcontractor_work_order_id: int,
     *   subcontractor_work_order?: array{id: int, sc_wo_number: string, name: string},
     *   net_amount: int,
     *   invoice_date: string,
     *   due_date: string,
     *   status: string,
     *   notes: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var \Carbon\Carbon|null $invoiceDate */
        $invoiceDate = $this->invoice_date;
        /** @var \Carbon\Carbon|null $dueDate */
        $dueDate = $this->due_date;
        /** @var \Carbon\Carbon|null $createdAt */
        $createdAt = $this->created_at;
        /** @var \Carbon\Carbon|null $updatedAt */
        $updatedAt = $this->updated_at;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'subcontractor_id' => $this->subcontractor_id,
            'subcontractor' => $this->whenLoaded('subcontractor', fn () => [
                'id' => $this->subcontractor->id,
                'name' => $this->subcontractor->name,
            ]),
            'subcontractor_work_order_id' => $this->subcontractor_work_order_id,
            'subcontractor_work_order' => $this->whenLoaded('subcontractorWorkOrder', fn () => [
                'id' => $this->subcontractorWorkOrder->id,
                'sc_wo_number' => $this->subcontractorWorkOrder->sc_wo_number,
                'name' => $this->subcontractorWorkOrder->name,
            ]),
            'gross_amount' => $this->gross_amount,
            'retention_held' => $this->retention_held,
            'other_deductions' => $this->other_deductions,
            'net_amount' => $this->net_amount,
            'invoice_date' => $invoiceDate?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'created_at' => $createdAt?->toIso8601String(),
            'updated_at' => $updatedAt?->toIso8601String(),
        ];
    }
}