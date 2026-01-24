<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shared\RecurringTemplate
 */
class RecurringTemplateResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   name: string,
     *   type: string,
     *   contact_id: int,
     *   contact?: ContactResource,
     *   frequency: string,
     *   frequency_label: string,
     *   interval: int,
     *   start_date: string|null,
     *   end_date: string|null,
     *   next_generate_date: string|null,
     *   occurrences_limit: int|null,
     *   occurrences_count: int,
     *   description: string|null,
     *   reference: string|null,
     *   tax_rate: float,
     *   discount_amount: int,
     *   early_discount_percent: float,
     *   early_discount_days: int,
     *   payment_term_days: int,
     *   currency: string,
     *   items: array<mixed>,
     *   is_active: bool,
     *   auto_post: bool,
     *   auto_send: bool,
     *   can_generate: bool,
     *   invoices_count?: int,
     *   bills_count?: int,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'frequency' => $this->frequency,
            'frequency_label' => $this->getFrequencyLabel(),
            'interval' => $this->interval,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_generate_date' => $this->next_generate_date?->toDateString(),
            'occurrences_limit' => $this->occurrences_limit,
            'occurrences_count' => $this->occurrences_count,
            'description' => $this->description,
            'reference' => $this->reference,
            'tax_rate' => (float) $this->tax_rate,
            'discount_amount' => $this->discount_amount,
            'early_discount_percent' => (float) $this->early_discount_percent,
            'early_discount_days' => $this->early_discount_days,
            'payment_term_days' => $this->payment_term_days,
            'currency' => $this->currency,
            'items' => $this->items,
            'is_active' => $this->is_active,
            'auto_post' => $this->auto_post,
            'auto_send' => $this->auto_send,
            'can_generate' => $this->shouldGenerate(),
            'invoices_count' => $this->whenLoaded('invoices', fn () => $this->invoices->count()),
            'bills_count' => $this->whenLoaded('bills', fn () => $this->bills->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function getFrequencyLabel(): string
    {
        $labels = [
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            'quarterly' => 'Triwulan',
            'yearly' => 'Tahunan',
        ];

        return $labels[$this->frequency] ?? $this->frequency;
    }
}
