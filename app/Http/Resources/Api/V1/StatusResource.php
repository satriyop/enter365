<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared Status API Resource.
 *
 * Used for consistent status object representation across all documents.
 *
 * @property \App\Enums\DocumentStatus|\App\Enums\BankTransactionStatus|\App\Enums\BudgetStatus|\App\Enums\MrpSuggestionStatus $resource
 */
class StatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *   value: string,
     *   label: string,
     *   color: string,
     *   is_terminal: bool,
     *   is_editable: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value,
            'label' => $this->resource->label(),
            'color' => $this->resource->color(),
            'is_terminal' => $this->resource->isTerminal(),
            'is_editable' => $this->resource->isEditable(),
        ];
    }
}
