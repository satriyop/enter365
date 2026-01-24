<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sales\QuotationActivity
 */
class QuotationActivityResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   quotation_id: int,
     *   user_id: int,
     *   user?: UserResource,
     *   type: string,
     *   type_label: string,
     *   contact_method: string,
     *   subject: string,
     *   description: string|null,
     *   activity_at: string,
     *   duration_minutes: int|null,
     *   formatted_duration: string,
     *   contact_person: string|null,
     *   contact_phone: string|null,
     *   next_follow_up_at: string|null,
     *   follow_up_type: string|null,
     *   outcome: string|null,
     *   outcome_label: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
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
