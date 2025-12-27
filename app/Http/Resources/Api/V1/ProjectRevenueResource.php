<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRevenueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'revenue_type' => $this->revenue_type,
            'description' => $this->description,
            'revenue_date' => $this->revenue_date->format('Y-m-d'),
            'amount' => $this->amount,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
            ]),
            'down_payment_id' => $this->down_payment_id,
            'down_payment' => $this->whenLoaded('downPayment', fn () => [
                'id' => $this->downPayment->id,
                'dp_number' => $this->downPayment->dp_number,
            ]),
            'milestone_name' => $this->milestone_name,
            'milestone_percentage' => $this->milestone_percentage ? (float) $this->milestone_percentage : null,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
