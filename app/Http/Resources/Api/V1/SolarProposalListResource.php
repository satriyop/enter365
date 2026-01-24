<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for solar proposal lists.
 *
 * @mixin \App\Models\Solar\SolarProposal
 */
class SolarProposalListResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   proposal_number: string,
     *   status: array{value: string, label: string, color: string},
     *   status_label: string,
     *   contact_id: int,
     *   contact?: ContactResource,
     *   site_name: string|null,
     *   province: string|null,
     *   city: string|null,
     *   system_capacity_kwp: float|null,
     *   system_cost: int,
     *   payback_years: float,
     *   roi_percent: float,
     *   valid_until: string|null,
     *   is_expired: bool,
     *   created_by: int|null,
     *   creator?: UserResource,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proposal_number' => $this->proposal_number,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'status_label' => $this->getStatusLabel(),

            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            'site_name' => $this->site_name,
            'province' => $this->province,
            'city' => $this->city,

            'system_capacity_kwp' => $this->system_capacity_kwp ? (float) $this->system_capacity_kwp : null,
            'system_cost' => $this->getSystemCost(),
            'payback_years' => $this->getPaybackPeriod(),
            'roi_percent' => $this->getRoi(),

            'valid_until' => $this->valid_until?->toDateString(),
            'is_expired' => $this->isExpired(),

            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
