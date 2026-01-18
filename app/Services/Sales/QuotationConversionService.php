<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Quotation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for quotation conversion operations.
 *
 * Handles converting approved quotations to invoices and other document types.
 */
class QuotationConversionService
{
    /**
     * Convert an approved quotation to an invoice.
     */
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        if (! $quotation->canConvert()) {
            throw new InvalidArgumentException('Penawaran tidak dapat dikonversi. Pastikan sudah disetujui dan belum dikonversi.');
        }

        return DB::transaction(function () use ($quotation) {
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
                'created_by' => auth()->id(),
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
                    'amount' => $item->line_total,
                ]);
            }

            // Update quotation using state machine (dispatches events)
            $quotation->update([
                'converted_to_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);
            $quotation->transitionTo(DocumentStatus::Converted);

            return $invoice->load('items', 'contact');
        });
    }

    /**
     * Check if a quotation can be converted to invoice.
     */
    public function canConvertToInvoice(Quotation $quotation): bool
    {
        return $quotation->canConvert();
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
