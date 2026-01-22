<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Models\Sales\Quotation;

class QuotationDefaults
{
    /**
     * Get default values for a new quotation.
     *
     * @param  array<string, mixed>  $data  Input data that may override defaults
     * @param  int  $userId  The user creating the quotation
     * @return array<string, mixed>
     */
    public function getForCreate(array $data, int $userId): array
    {
        $taxRate = (float) ($data['tax_rate'] ?? config('accounting.tax.default_rate', 11.00));
        $quotationDate = $data['quotation_date'] ?? now();
        $validityDays = config('accounting.quotation.default_validity_days', 30);

        return [
            'quotation_number' => $data['quotation_number'] ?? '',
            'contact_id' => $data['contact_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'currency' => $data['currency'] ?? 'IDR',
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'tax_rate' => $taxRate,
            'quotation_date' => $quotationDate,
            'valid_until' => $data['valid_until'] ?? now()->parse($quotationDate)->addDays($validityDays),
            'reference' => $data['reference'] ?? null,
            'subject' => $data['subject'] ?? null,
            'quotation_type' => $data['quotation_type'] ?? 'single',
            'variant_group_id' => $data['variant_group_id'] ?? null,
            'selected_variant_id' => $data['selected_variant_id'] ?? null,
            'source_bom_id' => $data['source_bom_id'] ?? null,
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'terms_conditions' => $data['terms_conditions'] ?? $this->getTermsConditions($data['locale'] ?? 'id'),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
            'created_by' => $userId,
        ];
    }

    /**
     * Get default values for a duplicated quotation.
     *
     * @param  Quotation  $source  The quotation to duplicate from
     * @return array<string, mixed>
     */
    public function forDuplication(Quotation $source): array
    {
        $validityDays = config('accounting.quotation.default_validity_days', 30);

        return [
            'contact_id' => $source->contact_id,
            'quotation_date' => now(),
            'valid_until' => now()->addDays($validityDays),
            'reference' => null,
            'subject' => $source->subject,
            'status' => 'draft',
            'currency' => $source->currency,
            'exchange_rate' => $source->exchange_rate,
            'subtotal' => $source->subtotal,
            'discount_type' => $source->discount_type,
            'discount_value' => $source->discount_value,
            'discount_amount' => $source->discount_amount,
            'tax_rate' => $source->tax_rate,
            'tax_amount' => $source->tax_amount,
            'total_amount' => $source->total_amount,
            'base_currency_total' => $source->base_currency_total,
            'notes' => $source->notes,
            'terms_conditions' => $source->terms_conditions,
        ];
    }

    /**
     * Get default values for a quotation created from a BOM.
     *
     * @param  array<string, mixed>  $data  Input data from request
     * @param  \App\Models\Manufacturing\Bom  $bom  The BOM being used
     * @param  int  $userId  The user creating the quotation
     * @return array<string, mixed>
     */
    public function forBom(array $data, $bom, int $userId): array
    {
        $taxRate = (float) ($data['tax_rate'] ?? config('accounting.tax.default_rate', 11.00));
        $quotationDate = $data['quotation_date'] ?? now();
        $validityDays = config('accounting.quotation.default_validity_days', 30);
        $marginPercent = $data['margin_percent'] ?? 20;

        $subject = $data['subject'] ?? $bom->name;
        if ($bom->variant_name) {
            $subject .= ' - '.$bom->variant_name;
        }

        $notes = $data['notes'] ?? "Dibuat dari BOM: {$bom->bom_number}\nMargin: {$marginPercent}%\nBiaya BOM: ".number_format($bom->total_cost, 0, ',', '.');

        return [
            'quotation_number' => '',
            'contact_id' => $data['contact_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'status' => 'draft',
            'currency' => $data['currency'] ?? 'IDR',
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'tax_rate' => $taxRate,
            'quotation_date' => $quotationDate,
            'valid_until' => $data['valid_until'] ?? now()->parse($quotationDate)->addDays($validityDays),
            'reference' => $data['reference'] ?? $bom->bom_number,
            'subject' => $subject,
            'quotation_type' => 'single',
            'variant_group_id' => null,
            'selected_variant_id' => null,
            'source_bom_id' => $bom->id,
            'discount_type' => null,
            'discount_value' => 0,
            'notes' => $notes,
            'terms_conditions' => $data['terms_conditions'] ?? $this->getTermsConditions($data['locale'] ?? 'id'),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
            'created_by' => $userId,
        ];
    }

    /**
     * Get default values for a revised quotation.
     *
     * @param  Quotation  $source  The quotation to revise from
     * @param  int  $originalId  The original quotation ID
     * @param  int  $revisionNumber  The new revision number
     * @return array<string, mixed>
     */
    public function forRevision(Quotation $source, int $originalId, int $revisionNumber): array
    {
        $validityDays = config('accounting.quotation.default_validity_days', 30);

        return [
            'quotation_number' => $source->quotation_number,
            'revision' => $revisionNumber,
            'contact_id' => $source->contact_id,
            'quotation_date' => now(),
            'valid_until' => now()->addDays($validityDays),
            'reference' => $source->reference,
            'subject' => $source->subject,
            'status' => 'draft',
            'currency' => $source->currency,
            'exchange_rate' => $source->exchange_rate,
            'subtotal' => $source->subtotal,
            'discount_type' => $source->discount_type,
            'discount_value' => $source->discount_value,
            'discount_amount' => $source->discount_amount,
            'tax_rate' => $source->tax_rate,
            'tax_amount' => $source->tax_amount,
            'total_amount' => $source->total_amount,
            'base_currency_total' => $source->base_currency_total,
            'notes' => $source->notes,
            'terms_conditions' => $source->terms_conditions,
            'original_quotation_id' => $originalId,
        ];
    }

    /**
     * Get default terms and conditions.
     */
    public function getTermsConditions(string $locale = 'id'): string
    {
        $template = config("accounting.quotation.terms_conditions.{$locale}", '');
        $validityDays = config('accounting.quotation.default_validity_days', 30);

        return str_replace('{validity_days}', (string) $validityDays, $template);
    }
}
