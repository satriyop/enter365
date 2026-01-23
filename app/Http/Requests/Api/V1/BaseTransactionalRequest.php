<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base class for transactional document requests (invoices, quotations, purchase orders, etc.)
 * Provides common validation rules to eliminate duplication.
 */
abstract class BaseTransactionalRequest extends FormRequest
{
    /**
     * Get common transactional validation rules.
     */
    protected function commonTransactionalRules(): array
    {
        return [
            'contact_id' => ValidationRules::CONTACT_REQUIRED,
            'subject' => ValidationRules::SUBJECT_OPTIONAL,
            'reference' => ValidationRules::REFERENCE_OPTIONAL,
            'currency' => ValidationRules::CURRENCY_OPTIONAL,
            'exchange_rate' => ValidationRules::EXCHANGE_RATE_OPTIONAL,
            'discount_type' => ValidationRules::DISCOUNT_TYPE_OPTIONAL,
            'discount_value' => ValidationRules::DISCOUNT_VALUE_OPTIONAL,
            'tax_rate' => ValidationRules::TAX_RATE_NULLABLE,
            'notes' => ValidationRules::NOTES_OPTIONAL,
            'terms_conditions' => ValidationRules::TERMS_OPTIONAL,
        ];
    }

    /**
     * Get common item array validation rules.
     */
    protected function commonItemRules(): array
    {
        return [
            'items' => ValidationRules::ITEMS_ARRAY,
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ValidationRules::ITEM_DESCRIPTION,
            'items.*.quantity' => ValidationRules::QUANTITY_REQUIRED,
            'items.*.unit' => ValidationRules::ITEM_UNIT_OPTIONAL,
            'items.*.unit_price' => ValidationRules::UNIT_PRICE_REQUIRED,
            'items.*.discount_percent' => ValidationRules::DISCOUNT_PERCENT_NULLABLE,
            'items.*.tax_rate' => ValidationRules::TAX_RATE_NULLABLE,
            'items.*.notes' => ValidationRules::ITEM_NOTES,
            'items.*.sort_order' => ValidationRules::ITEM_SORT_ORDER,
        ];
    }

    /**
     * Get common schedule validation rules for documents with dates.
     */
    protected function commonScheduleRules(): array
    {
        return [
            'scheduled_start_date' => ValidationRules::DATE_OPTIONAL,
            'scheduled_end_date' => ValidationRules::DATE_OPTIONAL,
            'actual_start_date' => ValidationRules::DATE_OPTIONAL,
            'actual_end_date' => ValidationRules::DATE_OPTIONAL,
        ];
    }
}
