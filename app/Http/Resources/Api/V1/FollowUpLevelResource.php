<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\FollowUpLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FollowUpLevel
 */
class FollowUpLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'delay_days' => $this->delay_days,
            'sequence' => $this->sequence,
            'send_email' => $this->send_email,
            'join_invoices' => $this->join_invoices,
            'message' => $this->message,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
