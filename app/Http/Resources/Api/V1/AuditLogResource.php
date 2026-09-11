<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Core\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'action' => $this->action,
            'auditable_type' => class_basename((string) $this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'auditable_label' => $this->auditable_label,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
