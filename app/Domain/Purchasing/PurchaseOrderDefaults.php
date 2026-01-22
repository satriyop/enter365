<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Models\Purchasing\PurchaseOrder;

class PurchaseOrderDefaults
{
    public const DEFAULT_TAX_RATE = 11.00;

    public const DEFAULT_CURRENCY = 'IDR';

    public const DEFAULT_EXCHANGE_RATE = 1;

    public const DEFAULT_VALIDITY_DAYS = 14;

    public function getForCreate(array $data, int $userId): array
    {
        return [
            'po_number' => '',
            'status' => 'draft',
            'currency' => $data['currency'] ?? self::DEFAULT_CURRENCY,
            'exchange_rate' => $data['exchange_rate'] ?? self::DEFAULT_EXCHANGE_RATE,
            'tax_rate' => (float) ($data['tax_rate'] ?? config('accounting.tax.default_rate', self::DEFAULT_TAX_RATE)),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
            'created_by' => $userId,
        ];
    }

    public function forDuplication(PurchaseOrder $source): array
    {
        return [
            'contact_id' => $source->contact_id,
            'po_date' => now(),
            'expected_date' => now()->addDays(self::DEFAULT_VALIDITY_DAYS),
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
            'shipping_address' => $source->shipping_address,
        ];
    }
}
