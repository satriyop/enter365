<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AnalyticBudget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnalyticBudget
 */
class AnalyticBudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'lines_count' => $this->whenCounted('lines'),
            'lines' => AnalyticBudgetLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
