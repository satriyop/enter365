<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationService
{
    /**
     * Create a new quotation with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Set defaults
            $data['quotation_number'] = Quotation::generateQuotationNumber();
            $data['status'] = Quotation::STATUS_DRAFT;
            $data['currency'] = $data['currency'] ?? 'IDR';
            $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
            $data['tax_rate'] = $data['tax_rate'] ?? config('accounting.tax.default_rate', 11.00);

            // Set validity if not provided
            if (empty($data['valid_until'])) {
                $quotationDate = $data['quotation_date'] ?? now();
                $validityDays = config('accounting.quotation.default_validity_days', 30);
                $data['valid_until'] = now()->parse($quotationDate)->addDays($validityDays);
            }

            // Set default terms if not provided
            if (empty($data['terms_conditions'])) {
                $data['terms_conditions'] = Quotation::getDefaultTermsConditions();
            }

            // Create quotation with zero totals first
            $data['subtotal'] = 0;
            $data['discount_amount'] = 0;
            $data['tax_amount'] = 0;
            $data['total'] = 0;
            $data['base_currency_total'] = 0;
            $data['created_by'] = auth()->id();

            $quotation = Quotation::create($data);

            // Create items
            $this->createItems($quotation, $items);

            // Calculate totals
            $quotation->refresh();
            $quotation->calculateTotals();
            $quotation->save();

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Create a quotation from a BOM.
     *
     * This allows salespeople to pick a specific BOM (e.g., from a variant group)
     * and auto-generate a quotation with proper pricing.
     *
     * @param  array<string, mixed>  $data  {
     *                                      bom_id: int,           Required - BOM to create quotation from
     *                                      contact_id: int,       Required - Customer contact
     *                                      margin_percent?: float, Margin percentage to add on top of BOM cost (default: 20)
     *                                      selling_price?: int,   Override: direct selling price (ignores margin)
     *                                      expand_items?: bool,   Expand BOM items as quotation lines (default: false)
     *                                      quotation_date?: date,
     *                                      valid_until?: date,
     *                                      subject?: string,
     *                                      reference?: string,
     *                                      notes?: string,
     *                                      terms_conditions?: string,
     *                                      tax_rate?: float,
     *                                      currency?: string,
     *                                      exchange_rate?: float,
     *                                      }
     */
    public function createFromBom(array $data): Quotation
    {
        $bomId = $data['bom_id'] ?? null;
        if (! $bomId) {
            throw new InvalidArgumentException('BOM harus dipilih.');
        }

        $bom = Bom::with(['items.product', 'product'])->find($bomId);
        if (! $bom) {
            throw new InvalidArgumentException('BOM tidak ditemukan.');
        }

        if ($bom->status !== Bom::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Hanya BOM dengan status aktif yang dapat digunakan.');
        }

        $marginPercent = $data['margin_percent'] ?? 20;
        $expandItems = $data['expand_items'] ?? false;

        // Calculate selling price
        $bomCost = $bom->total_cost ?? 0;
        if (isset($data['selling_price'])) {
            $sellingPrice = (int) $data['selling_price'];
        } else {
            $sellingPrice = (int) round($bomCost * (1 + ($marginPercent / 100)));
        }

        return DB::transaction(function () use ($data, $bom, $sellingPrice, $marginPercent, $expandItems) {
            $taxRate = $data['tax_rate'] ?? config('accounting.tax.default_rate', 11.00);
            $quotationDate = $data['quotation_date'] ?? now();
            $validityDays = config('accounting.quotation.default_validity_days', 30);

            // Build subject from BOM if not provided
            $subject = $data['subject'] ?? $bom->name;
            if ($bom->variant_name) {
                $subject .= ' - '.$bom->variant_name;
            }

            // Create quotation
            $quotation = Quotation::create([
                'quotation_number' => Quotation::generateQuotationNumber(),
                'revision' => 0,
                'contact_id' => $data['contact_id'],
                'quotation_date' => $quotationDate,
                'valid_until' => $data['valid_until'] ?? now()->parse($quotationDate)->addDays($validityDays),
                'reference' => $data['reference'] ?? $bom->bom_number,
                'subject' => $subject,
                'quotation_type' => Quotation::TYPE_SINGLE,
                'source_bom_id' => $bom->id,
                'status' => Quotation::STATUS_DRAFT,
                'currency' => $data['currency'] ?? 'IDR',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'tax_rate' => $taxRate,
                'subtotal' => 0,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'base_currency_total' => 0,
                'notes' => $data['notes'] ?? "Dibuat dari BOM: {$bom->bom_number}\nMargin: {$marginPercent}%\nBiaya BOM: ".number_format($bom->total_cost, 0, ',', '.'),
                'terms_conditions' => $data['terms_conditions'] ?? Quotation::getDefaultTermsConditions(),
                'created_by' => auth()->id(),
            ]);

            // Create items
            if ($expandItems) {
                $this->createItemsFromBomExpanded($quotation, $bom, $marginPercent);
            } else {
                $this->createItemsFromBomSingle($quotation, $bom, $sellingPrice);
            }

            // Calculate totals
            $quotation->refresh();
            $quotation->calculateTotals();
            $quotation->save();

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Create quotation items by expanding BOM items (detailed view).
     */
    private function createItemsFromBomExpanded(Quotation $quotation, Bom $bom, float $marginPercent): void
    {
        $sortOrder = 0;
        $multiplier = 1 + ($marginPercent / 100);

        foreach ($bom->items as $bomItem) {
            // Calculate selling price with margin
            $unitPrice = (int) round($bomItem->unit_cost * $multiplier);
            $quantity = (float) $bomItem->quantity;
            $lineTotal = (int) round($quantity * $unitPrice);

            // Get description
            $description = $bomItem->description;
            if ($bomItem->product) {
                $description = $bomItem->product->name;
                if ($bomItem->description) {
                    $description .= ' - '.$bomItem->description;
                }
            }

            // Add type indicator for clarity
            $typeLabel = match ($bomItem->type) {
                BomItem::TYPE_MATERIAL => '[Material]',
                BomItem::TYPE_LABOR => '[Jasa]',
                BomItem::TYPE_OVERHEAD => '[Overhead]',
                default => '',
            };

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $bomItem->product_id,
                'description' => trim("{$typeLabel} {$description}"),
                'quantity' => $quantity,
                'unit' => $bomItem->unit ?? 'unit',
                'unit_price' => $unitPrice,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_rate' => $quotation->tax_rate,
                'tax_amount' => (int) round($lineTotal * ($quotation->tax_rate / 100)),
                'line_total' => $lineTotal,
                'sort_order' => $sortOrder++,
                'notes' => $bomItem->notes,
            ]);
        }
    }

    /**
     * Create single quotation item from BOM (simplified view for customer).
     */
    private function createItemsFromBomSingle(Quotation $quotation, Bom $bom, int $sellingPrice): void
    {
        // Build description from BOM product
        $description = $bom->product?->name ?? $bom->name;
        if ($bom->variant_name) {
            $description .= ' ('.$bom->variant_name.')';
        }

        $taxAmount = (int) round($sellingPrice * ($quotation->tax_rate / 100));

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $bom->product_id,
            'description' => $description,
            'quantity' => (float) $bom->output_quantity ?? 1,
            'unit' => $bom->output_unit ?? 'system',
            'unit_price' => $sellingPrice,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_rate' => $quotation->tax_rate,
            'tax_amount' => $taxAmount,
            'line_total' => $sellingPrice,
            'sort_order' => 0,
            'notes' => $bom->description,
        ]);
    }

    /**
     * Update a quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        if (! $quotation->isEditable()) {
            throw new InvalidArgumentException('Hanya penawaran draft yang dapat diubah.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $quotation->update($data);

            if ($items !== null) {
                // Delete existing items and recreate
                $quotation->items()->delete();
                $this->createItems($quotation, $items);
            }

            // Recalculate totals
            $quotation->refresh();
            $quotation->calculateTotals();
            $quotation->save();

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->canSubmit()) {
            throw new InvalidArgumentException('Penawaran tidak dapat diajukan. Pastikan status draft dan memiliki item.');
        }

        $quotation->update([
            'status' => Quotation::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $userId ?? auth()->id(),
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->canApprove()) {
            throw new InvalidArgumentException('Penawaran tidak dapat disetujui. Pastikan sudah diajukan dan belum kedaluwarsa.');
        }

        $quotation->update([
            'status' => Quotation::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $userId ?? auth()->id(),
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation
    {
        if (! $quotation->canReject()) {
            throw new InvalidArgumentException('Penawaran tidak dapat ditolak. Pastikan sudah diajukan.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $quotation->update([
            'status' => Quotation::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $userId ?? auth()->id(),
            'rejection_reason' => $reason,
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Create a revision of a quotation.
     */
    public function revise(Quotation $quotation): Quotation
    {
        if (! $quotation->canRevise()) {
            throw new InvalidArgumentException('Penawaran tidak dapat direvisi. Pastikan sudah disetujui, ditolak, atau kedaluwarsa.');
        }

        return DB::transaction(function () use ($quotation) {
            $originalId = $quotation->original_quotation_id ?? $quotation->id;
            $nextRevision = $quotation->getNextRevisionNumber();

            // Create new quotation as revision
            $newQuotation = Quotation::create([
                'quotation_number' => $quotation->quotation_number,
                'revision' => $nextRevision,
                'contact_id' => $quotation->contact_id,
                'quotation_date' => now(),
                'valid_until' => now()->addDays(config('accounting.quotation.default_validity_days', 30)),
                'reference' => $quotation->reference,
                'subject' => $quotation->subject,
                'status' => Quotation::STATUS_DRAFT,
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'subtotal' => $quotation->subtotal,
                'discount_type' => $quotation->discount_type,
                'discount_value' => $quotation->discount_value,
                'discount_amount' => $quotation->discount_amount,
                'tax_rate' => $quotation->tax_rate,
                'tax_amount' => $quotation->tax_amount,
                'total' => $quotation->total,
                'base_currency_total' => $quotation->base_currency_total,
                'notes' => $quotation->notes,
                'terms_conditions' => $quotation->terms_conditions,
                'original_quotation_id' => $originalId,
                'created_by' => auth()->id(),
            ]);

            // Copy items
            foreach ($quotation->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $newQuotation->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                    'sort_order' => $item->sort_order,
                    'notes' => $item->notes,
                ]);
            }

            return $newQuotation->load('items', 'contact');
        });
    }

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
                'status' => Invoice::STATUS_DRAFT,
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

            // Update quotation
            $quotation->update([
                'status' => Quotation::STATUS_CONVERTED,
                'converted_to_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);

            return $invoice->load('items', 'contact');
        });
    }

    /**
     * Duplicate a quotation as a new draft.
     */
    public function duplicate(Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($quotation) {
            $newQuotation = Quotation::create([
                'quotation_number' => Quotation::generateQuotationNumber(),
                'revision' => 0,
                'contact_id' => $quotation->contact_id,
                'quotation_date' => now(),
                'valid_until' => now()->addDays(config('accounting.quotation.default_validity_days', 30)),
                'reference' => null,
                'subject' => $quotation->subject,
                'status' => Quotation::STATUS_DRAFT,
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'subtotal' => $quotation->subtotal,
                'discount_type' => $quotation->discount_type,
                'discount_value' => $quotation->discount_value,
                'discount_amount' => $quotation->discount_amount,
                'tax_rate' => $quotation->tax_rate,
                'tax_amount' => $quotation->tax_amount,
                'total' => $quotation->total,
                'base_currency_total' => $quotation->base_currency_total,
                'notes' => $quotation->notes,
                'terms_conditions' => $quotation->terms_conditions,
                'created_by' => auth()->id(),
            ]);

            // Copy items
            foreach ($quotation->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $newQuotation->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                    'sort_order' => $item->sort_order,
                    'notes' => $item->notes,
                ]);
            }

            return $newQuotation->load('items', 'contact');
        });
    }

    /**
     * Mark expired quotations.
     *
     * @return int Number of quotations marked as expired
     */
    public function markExpired(): int
    {
        return Quotation::query()
            ->whereIn('status', [Quotation::STATUS_DRAFT, Quotation::STATUS_SUBMITTED])
            ->where('valid_until', '<', now()->startOfDay())
            ->update(['status' => Quotation::STATUS_EXPIRED]);
    }

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Quotation::query();

        if ($startDate) {
            $query->where('quotation_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('quotation_date', '<=', $endDate);
        }

        $total = (clone $query)->count();
        $draft = (clone $query)->where('status', Quotation::STATUS_DRAFT)->count();
        $submitted = (clone $query)->where('status', Quotation::STATUS_SUBMITTED)->count();
        $approved = (clone $query)->where('status', Quotation::STATUS_APPROVED)->count();
        $rejected = (clone $query)->where('status', Quotation::STATUS_REJECTED)->count();
        $expired = (clone $query)->where('status', Quotation::STATUS_EXPIRED)->count();
        $converted = (clone $query)->where('status', Quotation::STATUS_CONVERTED)->count();

        $totalValue = (clone $query)->sum('total');
        $approvedValue = (clone $query)->where('status', Quotation::STATUS_APPROVED)->sum('total');
        $convertedValue = (clone $query)->where('status', Quotation::STATUS_CONVERTED)->sum('total');

        $approvalRate = $total > 0 ? round((($approved + $converted) / $total) * 100, 2) : 0;
        $conversionRate = ($approved + $converted) > 0 ? round(($converted / ($approved + $converted)) * 100, 2) : 0;

        return [
            'total' => $total,
            'by_status' => [
                'draft' => $draft,
                'submitted' => $submitted,
                'approved' => $approved,
                'rejected' => $rejected,
                'expired' => $expired,
                'converted' => $converted,
            ],
            'total_value' => $totalValue,
            'approved_value' => $approvedValue,
            'converted_value' => $convertedValue,
            'approval_rate' => $approvalRate,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Create quotation items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $quantity = $itemData['quantity'] ?? 1;
            $unitPrice = $itemData['unit_price'] ?? 0;
            $discountPercent = $itemData['discount_percent'] ?? 0;
            $taxRate = $itemData['tax_rate'] ?? $quotation->tax_rate;

            $grossAmount = (int) round($quantity * $unitPrice);
            $discountAmount = $discountPercent > 0
                ? (int) round($grossAmount * ($discountPercent / 100))
                : 0;
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * ($taxRate / 100));

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $itemData['product_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $quantity,
                'unit' => $itemData['unit'] ?? 'unit',
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $netAmount,
                'sort_order' => $itemData['sort_order'] ?? $index,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }
}
