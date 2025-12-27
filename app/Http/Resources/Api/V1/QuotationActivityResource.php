<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\QuotationActivity
 */
class QuotationActivityResource extends JsonResource
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
            'quotation_id' => $this->quotation_id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'contact_method' => $this->contact_method,

            'subject' => $this->subject,
            'description' => $this->description,
            'activity_at' => $this->activity_at->toIso8601String(),

            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->getFormattedDuration(),

            'contact_person' => $this->contact_person,
            'contact_phone' => $this->contact_phone,

            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'follow_up_type' => $this->follow_up_type,

            'outcome' => $this->outcome,
            'outcome_label' => $this->getOutcomeLabel(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
