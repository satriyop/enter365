<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shared\PaymentReminder
 */
class PaymentReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'remindable_type' => class_basename($this->remindable_type),
            'remindable_id' => $this->remindable_id,
            'remindable' => $this->whenLoaded('remindable', fn () => match (true) {
                $this->remindable instanceof Invoice => new InvoiceResource($this->remindable),
                $this->remindable instanceof Bill => new BillResource($this->remindable),
                default => null,
            }),
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'type' => $this->type,
            'days_offset' => $this->days_offset,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'sent_date' => $this->sent_date?->toDateString(),
            'status' => $this->status,
            'channel' => $this->channel,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
