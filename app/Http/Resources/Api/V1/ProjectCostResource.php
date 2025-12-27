<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectCostResource extends JsonResource
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
            'cost_type' => $this->cost_type,
            'description' => $this->description,
            'cost_date' => $this->cost_date->format('Y-m-d'),
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'bill_id' => $this->bill_id,
            'bill' => $this->whenLoaded('bill', fn () => [
                'id' => $this->bill->id,
                'bill_number' => $this->bill->bill_number,
            ]),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ]),
            'vendor_name' => $this->vendor_name,
            'is_billable' => $this->is_billable,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
