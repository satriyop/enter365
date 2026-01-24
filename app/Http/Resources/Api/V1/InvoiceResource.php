<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Invoices\InvoiceStateMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice API Resource.
 *
 * Transforms Invoice model for API responses with optional workflow
 * metadata, status history, and formatted amounts.
 *
 * @mixin \App\Models\Sales\Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   invoice_number: string,
     *   reference: string|null,
     *   description: string|null,
     *   contact_id: int,
     *   contact?: array{id: int, name: string, email: string|null},
     *   invoice_date: string|null,
     *   due_date: string|null,
     *   days_until_due: int,
     *   is_overdue: bool,
     *   days_overdue?: int,
     *   currency: string,
     *   exchange_rate: float,
     *   subtotal: int,
     *   discount_amount: int,
     *   tax_rate: float,
     *   tax_amount: int,
     *   total_amount: int,
     *   paid_amount: int,
     *   outstanding_amount: int,
     *   base_currency_total: int,
     *   formatted: array{
     *     subtotal: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     total_amount: string,
     *     paid_amount: string,
     *     outstanding_amount: string
     *   },
     *   status: array{
     *     value: string,
     *     label: string,
     *     color: string,
     *     is_terminal: bool,
     *     is_editable: bool
     *   },
     *   journal_entry_id: int|null,
     *   has_journal_entry: bool,
     *   journal_entry?: array{id: int, entry_number: string},
     *   receivable_account_id: int|null,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   item_count?: int,
     *   payments?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   payment_count?: int,
     *   actions: array{
     *     can_edit: bool,
     *     can_post: bool,
     *     can_cancel: bool,
     *     can_delete: bool,
     *     can_mark_as_paid: bool,
     *     can_mark_as_partial: bool
     *   },
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $stateMachine = InvoiceStateMachine::fromInvoice($this->resource);
        $outstandingAmount = $this->getOutstandingAmount();

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'reference' => $this->reference,
            'description' => $this->description,

            // Contact
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            // Dates
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'days_until_due' => $this->getDaysUntilDue(),
            'is_overdue' => $this->isOverdue(),
            $this->mergeWhen($this->isOverdue(), [
                'days_overdue' => $this->getDaysOverdue(),
            ]),

            // Currency
            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,

            // Amounts (raw integers for calculations)
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'outstanding_amount' => $outstandingAmount,
            'base_currency_total' => $this->base_currency_total,

            // Formatted amounts (for display)
            'formatted' => [
                'subtotal' => $this->formatMoney($this->subtotal),
                'discount_amount' => $this->formatMoney($this->discount_amount),
                'tax_amount' => $this->formatMoney($this->tax_amount),
                'total_amount' => $this->formatMoney($this->total_amount),
                'paid_amount' => $this->formatMoney($this->paid_amount),
                'outstanding_amount' => $this->formatMoney($outstandingAmount),
            ],

            // Status
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_terminal' => $this->status->isTerminal(),
                'is_editable' => $this->status->isEditable(),
            ],
            'status_label' => $this->status->label(),

            // Journal entry
            'journal_entry_id' => $this->journal_entry_id,
            'has_journal_entry' => $this->journal_entry_id !== null,
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),

            // Receivable account
            'receivable_account_id' => $this->receivable_account_id,

            // Items
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->whenCounted('items', fn () => $this->items_count),

            // Payments
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payment_count' => $this->whenCounted('payments', fn () => $this->payments_count),

            // Workflow (optional - include with ?include_workflow=true)
            'workflow' => $this->when(
                $request->boolean('include_workflow'),
                fn () => $stateMachine->getWorkflowMetadata()
            ),

            // Status history (optional - include with ?include_history=true)
            'status_history' => $this->when(
                $request->boolean('include_history'),
                fn () => $this->getStatusTimeline()
            ),

            // Available actions based on current state
            'actions' => [
                'can_edit' => $stateMachine->canEdit(),
                'can_post' => $stateMachine->canPost(),
                'can_cancel' => $stateMachine->canCancel(),
                'can_delete' => $stateMachine->canDelete(),
                'can_mark_as_paid' => $stateMachine->canMarkAsPaid(),
                'can_mark_as_partial' => $stateMachine->canMarkAsPartial(),
            ],

            // Metadata
            'reminder_count' => $this->reminder_count,
            'last_reminder_at' => $this->last_reminder_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Links (for HATEOAS-style responses)
            'links' => $this->when(
                $request->boolean('include_links'),
                fn () => [
                    'self' => route('invoices.show', $this->id),
                ]
            ),
        ];
    }

    /**
     * Format money amount in Indonesian Rupiah format.
     */
    private function formatMoney(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
