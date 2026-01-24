<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;

class QuotationItemCreator
{
    /**
     * Create quotation items from raw data.
     *
     * @param  Quotation  $quotation  The quotation to add items to
     * @param  array<int, array<string, mixed>>  $items  Raw item data
     */
    public function createItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $quantity = (float) ($itemData['quantity'] ?? 1);
            $unitPrice = (int) ($itemData['unit_price'] ?? 0);
            $discountPercent = (float) ($itemData['discount_percent'] ?? 0);
            $taxRate = (float) ($itemData['tax_rate'] ?? $quotation->tax_rate);

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

    /**
     * Create quotation items by expanding BOM items (detailed view for customer).
     *
     * @param  Quotation  $quotation  The quotation to add items to
     * @param  Bom  $bom  The BOM to create items from
     * @param  float  $marginPercent  Margin percentage to apply
     */
    public function createFromBomExpanded(Quotation $quotation, Bom $bom, float $marginPercent): void
    {
        $sortOrder = 0;
        $multiplier = 1 + ($marginPercent / 100);

        foreach ($bom->items as $bomItem) {
            $unitPrice = (int) round($bomItem->unit_cost * $multiplier);
            $quantity = (float) $bomItem->quantity;
            $lineTotal = (int) round($quantity * $unitPrice);

            $description = $bomItem->description;
            if ($bomItem->product) {
                $description = $bomItem->product->name;
                if ($bomItem->description) {
                    $description .= ' - '.$bomItem->description;
                }
            }

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
     *
     * @param  Quotation  $quotation  The quotation to add item to
     * @param  Bom  $bom  The BOM to create item from
     * @param  int  $sellingPrice  The selling price to use
     */
    public function createFromBomSingle(Quotation $quotation, Bom $bom, int $sellingPrice): void
    {
        $product = $bom->product;
        $description = $product ? $product->name : $bom->name;
        if ($bom->variant_name) {
            $description .= ' ('.$bom->variant_name.')';
        }

        $taxAmount = (int) round($sellingPrice * ($quotation->tax_rate / 100));

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $bom->product_id,
            'description' => $description,
            'quantity' => (float) ($bom->output_quantity ?? 1),
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
     * Copy items from one quotation to another.
     *
     * @param  Quotation  $source  Source quotation
     * @param  Quotation  $target  Target quotation
     */
    public function copyFromQuotation(Quotation $source, Quotation $target): void
    {
        foreach ($source->items as $item) {
            QuotationItem::create([
                'quotation_id' => $target->id,
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
    }
}
