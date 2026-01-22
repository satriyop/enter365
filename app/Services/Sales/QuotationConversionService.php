<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\QuotationConversionServiceInterface;
use App\Domain\Sales\Quotations\QuotationDomainFactory;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Quotation;
use App\Services\Base\BaseService;
use InvalidArgumentException;

/**
 * Service for quotation conversion operations.
 *
 * Handles converting approved quotations to invoices and other document types.
 */
class QuotationConversionService extends BaseService implements QuotationConversionServiceInterface
{
    public function __construct(
        private QuotationDomainFactory $domainFactory,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Convert an approved quotation to an invoice.
     */
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        $stateMachine = $this->domainFactory->stateMachine($quotation);
        if (! $stateMachine->canConvert()) {
            $reason = $stateMachine->getConversionBlockReason()
                ?? 'Penawaran tidak dapat dikonversi.';
            throw new InvalidArgumentException($reason);
        }

        return $this->executeInTransaction('convert_to_invoice', function () use ($quotation) {
            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'contact_id' => $quotation->contact_id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(config('accounting.payment.default_term_days', 30)),
                'description' => $quotation->subject,
                'reference' => $quotation->getFullNumber(),
                'subtotal' => $quotation->subtotal,
                'tax_amount' => $quotation->tax_amount,
                'tax_rate' => $quotation->tax_rate,
                'discount_amount' => $quotation->discount_amount,
                'total_amount' => $quotation->total,
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'base_currency_total' => $quotation->base_currency_total,
                'paid_amount' => 0,
                'status' => DocumentStatus::Draft,
                'created_by' => $this->getUserId(),
            ]);

            // Copy items
            foreach ($quotation->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]);
            }

            // Clear follow-up data (quotation is now closed)
            $quotation->next_follow_up_at = null;

            // Auto-mark as Won if outcome not already set
            if ($quotation->outcome === null) {
                $quotation->outcome = 'won';
                $quotation->won_reason = 'converted_to_invoice';
                $quotation->outcome_at = now();
            }

            // Update quotation using state machine (dispatches events)
            $quotation->update([
                'converted_to_invoice_id' => $invoice->id,
                'converted_at' => now(),
                'next_follow_up_at' => null,
                'outcome' => $quotation->outcome,
                'won_reason' => $quotation->won_reason,
                'outcome_at' => $quotation->outcome_at,
            ]);

            // Transition using state machine with proper event dispatch
            $this->domainFactory->stateMachine($quotation)->transitionTo(DocumentStatus::Converted);

            return $invoice->load('items', 'contact');
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Check if a quotation can be converted to invoice.
     */
    public function canConvertToInvoice(Quotation $quotation): bool
    {
        return $this->domainFactory->stateMachine($quotation)->canConvert();
    }

    /**
     * Get conversion status for a quotation.
     *
     * @return array{can_convert: bool, converted: bool, converted_to: array|null, reason: string|null}
     */
    public function getConversionStatus(Quotation $quotation): array
    {
        if ($quotation->status === DocumentStatus::Converted) {
            return [
                'can_convert' => false,
                'converted' => true,
                'converted_to' => [
                    'type' => 'invoice',
                    'id' => $quotation->converted_to_invoice_id,
                    'converted_at' => $quotation->converted_at,
                ],
                'reason' => 'Sudah dikonversi ke invoice.',
            ];
        }

        if ($quotation->status !== DocumentStatus::Approved) {
            return [
                'can_convert' => false,
                'converted' => false,
                'converted_to' => null,
                'reason' => 'Penawaran harus disetujui terlebih dahulu.',
            ];
        }

        return [
            'can_convert' => true,
            'converted' => false,
            'converted_to' => null,
            'reason' => null,
        ];
    }
}
